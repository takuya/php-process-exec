## プロセス起動をする

時間のかかるプロセスをイベントハンドラで呼び出せるようにした。

### 使用例

bashで文字列を実行する。
```php
<?php
$executor = new ProcessExecutor(new ExecArgStruct('bash'));
$executor->setInput('
for i in {0..4}; do
  echo $i
done;
');
$executor->start();
//blocking io
echo $executor->getOutput();
```
文字列出力で`なにか`する。
```php
<?php
$arg = new ExecArgStruct('php');
$src =<<<'EOS'
<?php
foreach(range(0,4) as $i){
  printf("%d\n",$i);
}
EOS;
$arg->setInput( $src );
$executor = new ProcessExecutor( $arg );
$executor->onStdOut(function ($line){ //=>１行ごとに処理
  echo $line.PHP_EOL;
});
$executor->start();
```

プロセスの終了時に`なにか`する。
```php
<?php
// プロセスを起動する基本設定
$arg = new ExecArgStruct();
$arg->setCmd( ['php'] );
$src = <<<'EOS'
<?php echo 'Hello World';
EOS;
$arg->setInput( $src );
// プロセス状態によってコールバック
$observer = new ProcessObserver();
$observer->addEventListener( ProcessErrorOccurred::class, fn()=> fwrite("php://stderr","エラー") );
$observer->addEventListener( ProcessFinished::class, fn($ev) =>print($ev->getExecutor()->getOutput()) );
// プロセス起動
$executor = new ProcessExecutor( $arg );
$executor->addObserver( $observer );
$executor->start();
```
### シェルを経由しないコマンド実行

「コマンド実行は**避けるべき**。」と習った人もいるだろう。コマンド実行のコードを書くこと自体を禁止される職場もあるそうだ。
コマンド実行禁止される理由の一つに、シェルのエスケープがある。文字列のエスケープ処理に問題がある。

だったら、エスケープが不要な実行方法で実行すればいい。

phpであれば、`proc_open`にArrayを渡すのがそれに相当する。
```php
<?php
$file_name = 'my long spaced doc.txt';
proc_open(['cat',$file_name]...);
```

ほかにもディレクトリトラバーサルに関する脆弱性考えられる。
こちらに関しては、シェル実行しないのであれば事前にプログラミングでチェックが可能である。
```php
<?php
$file_name = '../../../../../../../../etc/shadow';
$file_name = realpath('/my/app_root/'.basename($file_name);
proc_open(['cat',$file_name]...);
```

これらのことから、`proc_open`をちゃんと使ったコマンド実行は安全である。と言える。

それでも`proc_open`自体が面倒なので、`ProcOpen`というラッパーを作った。
```php
<?php
$proc = new ProcOpen(['cat',$fname]);
$proc->start();
echo stream_get_contents($proc->stdout());
```
これでも、まだ不満だった。
コマンドの起動設定（引数＋配列＋出力先）を使いまわすのが大変なので、コマンドに関する情報をStructにまとめた。
```php
<?php
$struct = new ExecArgStruct(['cat',$fname]);
$proc = new ProcessExecutor($struct);
$proc->start();
echo $proc->getOutput();
// 使いまわし
$proc = new ProcessExecutor($struct);
$proc->start();
echo $proc->getOutput();
```

これで、起動可能なコマンドを制限するアクセス可能なファイル制限や必須オプション追加チェックなどを`ExecArgStruct`を継承したクラス内でチェックができるようになる。
役割分担をすることでコードがスッキリする。

たとえば、次のように、利用可能なコマンドをチェックしたり。
```php
<?php
class RestrictedArg extends ExecArgStruct {
  public function __construct(...){
    // check allow command.
    $this->check() || throw new InvalidArgumentException();
  }
}
// InvalidArgumentException
$struct = new RestrictedArg(['passwd',$name]);
```
実行するコマンドの必須オプションをチェックしたり。
```php
<?php
class MyFFmpegStruct extends ExecArgStruct {
  public function __construct(...){
    // check command options.
    $this->checkOptions() || throw new \Exception('you need "-f" option ');
  }
}
//-> InvalidArgumentException
$struct = new MyFFmpegStruct(['ffmpeg','-i',$name]);
```

このパッケージでは、安全な「コマンド実行」のための工夫ができるようにした。
### イベント・リスナ

イベントをつかって「プロセスが〇〇したら、〇〇する。」

proc_openでプロセスの実行中・終了・失敗処理を書くとコードが煩雑になる。 解決のため、すべてを事前にコールバック登録できるようにした。
```php
<?php
// コマンド構造を定義
$arg = new ExecArgStruct('php -i');
$executor = new ProcessExecutor($arg);
// オブザーバー(リスナ集合体）を準備
$observer = new ProcessObserver();
$observer->addEventListener(ProcessStarted::class, fn()=>dump('started')));
$observer->addEventListener(ProcessSuccess::class, fn()=>dump('successfully finisihed')));
$observer->addEventListener(ProcessRunning::class, fn()=>dump('running')));
$observer->addEventListener(ProcessRunning::class, function(ProcessRunning $ev){
  // 同一イベントに複数登録も可能
  printf("pid=%d",$ev->getExecutor()->getProcess()->info->pid);
});
// 複数のオブザーバも利用可能
$streamObs = new ProcessObserver();
$streamObs->addEventListener(StdoutChanged::class, fn()=>dump('stdout changed')));
// 紐付ける。
$executor->addObserver($observer);
$executor->addObserver($streamObs);
$executor->start();
```

イベントは、次の通り準備した。

| クラス                  | 説明                                                       |
|:---------------------|:---------------------------------------------------------|
| ProcessReady         | 初期化時                                                     |
| ProcessStarted       | ProcessStartedは、プロセス起動時の初回だけ呼ばれる。以降はProcessRunningが呼ばれる。 |
| ProcessRunning       | 実行中(約0.001secごと)                                         |
| ProcessErrorOccurred | エラー時                                                     |
| ProcessCanceled      | シグナル検出                                                   |
| ProcessSucceed       | 正常終了時                                                    |
| ProcessFinished      | ProcessFinishedは、成功時も失敗時も両方。                             |
| StdoutChanged        | STDOUTに変化があったとき                                          |
| StderrChanged        | STDERRに変化があったとき                                          |


イベント・オブザーバーは、ProcessExecutor自身がストリームイベントの検出にも利用している。

#### onStdOut / onStdErr
StdoutChangedでオブザーバーを記述せずに済むようにオブザーバーを使ったシンプルなリスナ機構をビルトインしてある。

```php
<?php
$executor = new ProcessExecutor( new ExecArgStruct(['cat','file']) );
$executor->onStdOut(function ($line){
  echo $line.PHP_EOL;
});
$executor->start();

```
### onInputProgress / progress input (pv)
pvコマンドのパーセント表示の相当機能をビルトインした。
```php
<?php
$executor = new ProcessExecutor( new ExecArgStruct(['cat','-']) );
$executor->setInput(fopen('file','r'));
$executor->onInputProgress(fn($percent)=>printf("%s%%\n",$percent));
$executor->start();
```

コマンドがどこまで元ファイルを読み込んだかパーセントで測定できる。

ただし、まだ不安定。速度調整がいまいち。

### パイプ(pipe)起動
シェルでパイプを呼び出す。`pipe(|)`の例。
```shell
cat /etc/passwd | grep takuya
```
パイプを使ったシェルコマンドを`純粋なproc_open()`で書くと、**とてもめんどくさい**.
```php
<?php
$p1_fd_res = [['pipe','r'],['pipe','w'],['pipe','w']];
$p1 = proc_open(['ls','/etc'],$p1_fd_res,$p1_pipes);
fclose($p1_pipes[0]);
$p2_fd_res = [$p1_pipes[1],['pipe','w'],['pipe','w']];
$p2 = proc_open(['grep','su'],$p2_fd_res,$p2_pipes);

while(proc_get_status($p1)["running"]){
usleep(100);
}
while(proc_get_status($p2)["running"]){
usleep(100);
}
//
$str = fread($p2_pipes[1],1024);
var_dump($str);
```

`ProcOpen`クラスを作って`proc_open()`からのパイプ起動を使いやすくした。
```php
<?php
$p1 = new ProcOpen(['/bin/echo','<?php echo "Hello";']);
$p1->start();
$p2 = new ProcOpen(['/usr/bin/php']);
$p2->setInput($p1->stdout());
$p2->start();
$p1->wait();
$p2->wait();
//
echo stream_get_contents($p2->stdout()); //=> Hello
```
さらに抽象度を高めた書き方をサポートした。
```php
<?php
$arg1 = new ExecArgStruct('bash');
$arg1->setInput( <<<EOS
  echo -n '<?php echo "Hello World".PHP_EOL;';
  EOS );
$e1 = new ProcessExecutor( $arg1 );
$e2 = new ProcessExecutor( new ExecArgStruct('php') );
$e1->pipe($e2);
$out = $e2->getOutput();
echo $out
```
### パイプ起動で２つのStdErrorを読み込む。

たとえば、次のように`pv x.mp4| ffmpeg -i pipe:0` を起動する場合
```shell
"pv -f -L 2M work.mp4 | ffmpeg -y -i pipe:0 -s 1280x720 -movflags faststart out.mp4"
```
通常のシェルでは次のように、STDERRに出力されて、お互いに消し合ってしまう。
```shell
500KiB 0:00:02 [ 251KiB/s] [==============>                   ] 46% ETA 0:00:02\r
frame=  0 fps=0.0 q=0.0  size=   0kB time=00:00:01.42 bitrate=   0.3kbits/s speed=1.47x\r
```

プロセスごとに、STDEERを別に書き出せばいい。(`cmd 2>err.txt`) しかしログ閲覧は煩雑である。

```shell
## バックグラウンドで起動して
pv -f -L 2M work.mp4 2>err.1.txt | \
ffmpeg -y -i pipe:0 -s 1280x720 -movflags faststart out.mp4" 2> err.2.txt \
&

## tailで２つ起動してログを見る。
tail -f err.1.txt err.2.txt
```

シェル(bash)で出力ファイルを使わず、プログラミングで直接的にErrorストリームを扱えれば、ログ取得はスッキリする。 それをこのパッケージで実現する。

```php
<?php
// パイプでつないで
$pv = new ExecArgStruct( 'pv -f -L 2M work.mp4' );
$ffmpeg = new ExecArgStruct('ffmpeg -i pipe:0 -s 1280x720 -movflags faststart out.mp4');
$p1 = new ProcessExecutor( $pv );
$p2 = new ProcessExecutor( $ffmpeg );
$p1->pipe( $p2 );
// STDERRのログをそれぞれで管理する。
$p1->onStderr( fn( $progress ) => dump("pv: ".$progress) , "\r" );
$p2->onStderr( fn( $enc_stat ) => dump("ffmpeg: ".$enc_stat), "\r" );
```
結果は次のように、プロセス別個にSTDERRを取得できる。
```text
"pv:  500KiB 0:00:02 [ 251KiB/s] [==============>                   ] 46% ETA 0:00:02"
"pv:  750KiB 0:00:03 [ 251KiB/s] [======================>           ] 70% ETA 0:00:01"
"pv: 1000KiB 0:00:04 [ 251KiB/s] [==============================>   ] 93% ETA 0:00:00"
"pv: 1.05MiB 0:00:04 [ 251KiB/s] [================================>] 100%            "
"ffmpeg: frame=  0 fps=0.0 q=0.0  size=   0kB time=00:00:01.42 bitrate=   0.3kbits/s speed=1.47x"
"ffmpeg: frame= 44 fps= 16 q=29.0 size=   0kB time=00:00:03.32 bitrate=   0.1kbits/s speed=1.24x"
"ffmpeg: frame= 80 fps= 21 q=29.0 size= 256kB time=00:00:04.54 bitrate= 461.6kbits/s speed=1.21x"
```
上記の例のように、STDERRを別々のストリームとして処理できる。

## このパッケージに含まれるクラス

|               クラス | 説明                               |
|------------------:|:---------------------------------|
|        `ProcOpen` | `proc_open()`のラッパー               |
|   `ExecArgStruct` | コマンド構造を定義するクラス                   |
| `ProcessExecutor` | `ProcOpen`のラッパーでイベントを管理する        |
| `ProcessObserver` | `ProcessExecutor`で発火するイベントのリスナ管理 |
|        `StreamIO` | fopenされた`stream resource`を隠蔽する   |
|    `StreamReader` | ジェネレーター。`StreamIO`から１行単位で読み出す。   |

## 注意点

#### 注意１
Linux の PIPE_BUF / PIPE_SIZE に影響を受けるので注意。
stdout や stderr を読み出さずに大量に書き出すと、Linuxのpipeはバッファが詰まってプロセスから書き出せずに、プロセスが停止します。

`PIPE_SIZE=65,536 bytes`なので、64kBがstdoutに貯まると、プロセスはそれ以上をStdoutに書き出しできずにストップする。

適切に読み出すか、出力ファイルを指定する。`ffmpeg`や`imagemagick`を`proc_open`してSTDOUT書き出しすると、停止してしまいます。

このパッケージでは、**意図的に**、敢えて止まように設計している。出力先は自分で指定する。
```php
<?php
// stdout の io blocking で止まる
$arg = ExecArgStruct('ffmpeg -i input.mp4 -s 1280x720 -f mp4 pipe:1');
$ffmpeg = new ProcessExecutor( $arg );
$ffmpeg->start();
// output.binに書かれるので、止まらない。
$arg = ExecArgStruct('ffmpeg -i input.mp4 -s 1280x720 -f mp4 pipe:1');
$arg->setStdout(fopen('output.bin','w'));
$ffmpeg = new ProcessExecutor( $arg );
$ffmpeg->start();
```

#### 注意２

daemon化させる`ForkedExecutor`はsemaphoreやSharedMemoryを使う。

セマフォ(semaphore)や共有メモリ(SharedMemory)を使う場合。確保したまま終了するとサイズ不足で新規作成で着なくなります。
確保できずにプログラムが固まります。

Ctrl-Cでなどで中断したあとは、セマフォ・SharedMemoryを確認すること

次のコマンドを使って手動で管理を徹底する（とくにmacOS。macOSは利用可能なサイズが少ないので完璧な片付けを徹底する。）
```shell
ipcs -a
ipcmr -m $id
## 例
ipcs -a | \grep `whoami` | awk '{print $2}' | xargs  -I@ ipcrm -m @
ipcs -a | \grep `whoami` | awk '{print $2}' | xargs  -I@ ipcrm -s @
```

#### 注意３
POSIXシグナルを検出するために、次の１行を書いた方がいい。

これを書かないと、OSシグナル検出ができない。（ 参考資料:[PHPとシグナル、その裏側
](https://www.slideshare.net/do_aki/20171008-signal-onphp) )
```
pcntl_async_signals( true )
```
`ProcessExecutor`では暗黙的に実行するが、`ProcOpen`では明示的に実行する必要がある。

## インストール

TODO

## テスト
phpunit でテストする。
```shell
composer install 
vendor/bin/phpunit 
vendor/bin/phpunit --filter ProcOpenTest
```

## コードカバレッジ

コードカバレッジをphpunitで出す場合。

```shell
XDEBUG_MODE=debug,coverage vendor/bin/phpunit --coverage-html coverage
```

## TODO:
- Linux pip max に達した時点で stdout / stderr を読み込みバッファリングする。
- tty のサポート
    -  stream_isatty で調べて、Y/Nを送信できるようにする。

## todo:
2024-09-15
出力のバッファリングはやっぱりデフォルトでいれる必要がある。

ffprobeとか出力数が少ないと思って、適当に決め打ちで書いて詰まった。

```sh
// ffprobeを起動するだけでもめんどくさい。
//
$args = $this->buildCmd( $path, $opts );
if ( !is_readable($path)){
  throw new \RuntimeException("path is not readable ( {$path} ) ");
}
$p = new ProcessExecutor( $args );
$out_buff='';
$p->onStdout(function($line)use(&$out_buff){ $out_buff.=$line.PHP_EOL;}, PHP_EOL );
$p->start();
return [1 => $out_buff, 2 => $p->getErrout()];

```
