## Exec Process by php .

This help to run process by fork ( proc_open ). This package depends on [proc_open wrapper class](https://github.com/takuya/php-proc_open-wrapper). 

Handling process, *Run Long time command* safety. and Event handler model. 

## Installing

```shell

```

### Example

Run CMD STRING by bash.
```php
<?php
$executor = new ProcessExecutor(['bash']);
$executor->setInput('
for i in {0..4}; do
  echo $i
done;
');
$executor->start();
//blocking io
echo $executor->getOutput();
```

Do `something` at get output as line by line. 
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
$executor->onStdOut(function ($line){ //=> each line.
  echo $line.PHP_EOL;
});
$executor->start();
```
Do `something` after process finished.

```php
<?php
// process argument as class
$arg = new ExecArgStruct();
$arg->setCmd( ['php'] );
$src = <<<'EOS'
<?php echo 'Hello World';
EOS;
$arg->setInput( $src );
// run callback by running status.
$observer = new ProcessObserver();
$observer->addEventListener( ProcessErrorOccurred::class, fn()=> fwrite("php://stderr","エラー") );
$observer->addEventListener( ProcessFinished::class, fn($ev) =>print($ev->getExecutor()->getOutput()) );
// start process.
$executor = new ProcessExecutor( $arg );
$executor->addObserver( $observer );
$executor->start();
```
### Avoiding shell at run command.

run command without shell.

`"Run Command should be avoided in php"` , you may have been lectured. One of this reason is `SHELL ARGs ESCAPING`.
Shell string escaping is a troublemaker.

It's easy enough that run command without shell escaping. In php shell exec, pass Array to `proc_opee`. it can do that.

```php
<?php
# avoid Shell Injection vulnerability by pass as array
# Skip shell escaping.
$file_name = 'my long spaced doc.txt';
proc_open(['cat',$file_name]...);
```

Another reason for avoid shell execution is Directory Traversal vulnerability.

This can be avoided by checking values in advance.
```php
<?php
# avoid Directory traversal vulnerability by check path.
$file_name = '../../../../../../../../etc/shadow';
$file_name = realpath('/my/app_root/'.basename($file_name);
proc_open(['cat',$file_name]...);
```

These ways show that properly use of `proc_open` is SAFE. 

Even though,  `proc_open` itself is troublesome function. so I wrote [proc_open wrapper class](https://github.com/takuya/php-proc_open-wrapper) 
```php
<?php
## using proc_open as Class
$proc = new ProcOpen(['cat',$fname]);
$proc->start();
echo stream_get_contents($proc->stdout());
```
Nonetheless, I still have frustration. when **Re-using** of COMMAND config (params+args+redirect) is messy. So I wrote a class to make Arguments as class instance ( command options ).

```php
<?php
##  command option as struct
$struct = new ExecArgStruct(['cat',$fname]);
$proc = new ProcessExecutor($struct);
$proc->start();
echo $proc->getOutput();
// reusable ARGS and easy to run multiple times.
$proc = new ProcessExecutor($struct);
$proc->start();
echo $proc->getOutput();
```

By using Struct, we can check and validate arguments, such as indispensability argument, restrict command, check file accessible, before run.

Dividing role EXECUTION and VALIDATION , make classes simple. 


Example: argument checking and validation as construct.
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

Checking indispensable options, like this.
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

This package supports to run command `more safer` and `more easier` than proc_open().

### Event Trigger / Event Listener 

Using Event, " Process changed, then do something".

`proc_open` with handling process status ( running / finished / error ) increase complexity. Solving this, Event-Listeners can be added.

```php
<?php
// define Struct of CMD
$arg = new ExecArgStruct('php -i');
$executor = new ProcessExecutor($arg);
// Observer( aggregate of Listener ).
$observer = new ProcessObserver();
$observer->addEventListener(ProcessStarted::class, fn()=>dump('started')));
$observer->addEventListener(ProcessSuccess::class, fn()=>dump('successfully finisihed')));
$observer->addEventListener(ProcessRunning::class, fn()=>dump('running')));
$observer->addEventListener(ProcessRunning::class, function(ProcessRunning $ev){
  // Listeners can be added per Event.
  printf("pid=%d",$ev->getExecutor()->getProcess()->info->pid);
});
// Second Observer.
$streamObs = new ProcessObserver();
$streamObs->addEventListener(StdoutChanged::class, fn()=>dump('stdout changed')));
// Bind Observer with CMD executor
$executor->addObserver($observer);
$executor->addObserver($streamObs);
$executor->start();
```

Process Events are these.

| クラス                  | 説明                                                                     |
|:---------------------|:-----------------------------------------------------------------------|
| ProcessReady         | initialized                                                            |
| ProcessStarted       | STARTED: Process Started, once called after this ProcessRunning called |
| ProcessRunning       | RUNNING: in execution( per 0.001sec)                                   |
| ProcessErrorOccurred | ERROR Process exit with error.  non zero status.                       |
| ProcessCanceled      | CANCELED Signal Detected.                                              |
| ProcessSucceed       | SUCCESS Exit Successfully                                              |
| ProcessFinished      | FINISHED (both error and success)                                      |
| StdoutChanged        | STDOUT has been changed.                                               |
| StderrChanged        | STDERR has been changed.                                               |


Event Observer is used internal of this package, in ProcessExecutor, observer is used to detect IO Streaming.

#### onStdOut / onStdErr

Skip to write `new Observer`, Simplified Listener shortcut callback function is included in ProcessExecutor.
```php
<?php
## simple listener.
$executor = new ProcessExecutor( new ExecArgStruct(['cat','file']) );
$executor->onStdOut(function ($line){
  echo $line.PHP_EOL;
});
$executor->start();

```
### onInputProgress / progress input (pv) 

Input Percentage, ( like pv command ) callback function included. 

```php
<?php
$executor = new ProcessExecutor( new ExecArgStruct(['cat','-']) );
$executor->setInput(fopen('file','r'));
$executor->onInputProgress(fn($percent)=>printf("%s%%\n",$percent));
$executor->start();
```

`onInputProgress` pass a percentage of input has read.

Notice : this is not stable, reading speed limitation.

### Pipe ( piping process )

This is shell pipe sample `pipe(|)`.

```shell
cat /etc/passwd | grep takuya
```

Piping command line in shell by `pure proc_open()` is very **confusing**. 

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

I wrote `ProcOpen` class to make easier for using pipe process than `proc_open`.
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
Supporting `pipe()` as function, raise the abstraction level of pipe process.
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
### Two STDERR in Pipe , read separately 

For example, run 2 process `pv x.mp4| ffmpeg -i pipe:0` like this.
```shell
"pv -f -L 2M work.mp4 | ffmpeg -y -i pipe:0 -s 1280x720 -movflags faststart out.mp4"
```
Normal shell (ex. bash ), output is write to stderr(2), and '\r' will cancel each other stderr.
```shell
500KiB 0:00:02 [ 251KiB/s] [==============>                   ] 46% ETA 0:00:02\r
frame=  0 fps=0.0 q=0.0  size=   0kB time=00:00:01.42 bitrate=   0.3kbits/s speed=1.47x\r
```

To resolve this, Separate STDERR per process and individually out (`cmd 2>err.txt`). but, this way , reading stderr is messy.

```shell
## background and individually output.
pv -f -L 2M work.mp4 2>err.1.txt | \
ffmpeg -y -i pipe:0 -s 1280x720 -movflags faststart out.mp4" 2> err.2.txt &

## tail 2 log files. 
tail -f err.1.txt err.2.txt
```


Without shell (bash) output file, directory access to To STDERR by programme, Reading Two of STDERR is very simple.
This package supports two of stderr in pipe. 
```php
<?php
// Pipe process
$pv = new ExecArgStruct( 'pv -f -L 2M work.mp4' );
$ffmpeg = new ExecArgStruct('ffmpeg -i pipe:0 -s 1280x720 -movflags faststart out.mp4');
$p1 = new ProcessExecutor( $pv );
$p2 = new ProcessExecutor( $ffmpeg );
$p1->pipe( $p2 );
// Each STDERR can be access.
$p1->onStderr( fn( $progress ) => dump("pv: ".$progress) , "\r" );
$p2->onStderr( fn( $enc_stat ) => dump("ffmpeg: ".$enc_stat), "\r" );
```
Result is this , Each STDERR printed separately.Each STDERR can handle as each stream.
```text
"pv:  500KiB 0:00:02 [ 251KiB/s] [==============>                   ] 46% ETA 0:00:02"
"pv:  750KiB 0:00:03 [ 251KiB/s] [======================>           ] 70% ETA 0:00:01"
"pv: 1000KiB 0:00:04 [ 251KiB/s] [==============================>   ] 93% ETA 0:00:00"
"pv: 1.05MiB 0:00:04 [ 251KiB/s] [================================>] 100%            "
"ffmpeg: frame=  0 fps=0.0 q=0.0  size=   0kB time=00:00:01.42 bitrate=   0.3kbits/s speed=1.47x"
"ffmpeg: frame= 44 fps= 16 q=29.0 size=   0kB time=00:00:03.32 bitrate=   0.1kbits/s speed=1.24x"
"ffmpeg: frame= 80 fps= 21 q=29.0 size= 256kB time=00:00:04.54 bitrate= 461.6kbits/s speed=1.21x"
```

## Classes in This EXEC package 

|             Class | 説明                                            |
|------------------:|:----------------------------------------------|
|        `ProcOpen` | `proc_open()` wrapper                         |
|   `ExecArgStruct` | Command Argument Structs for                  |
| `ProcessExecutor` | `ProcOpen` Wrapper for EventHandling          |
| `ProcessObserver` | `ProcessExecutor` Aggregator of Process Event |
|        `StreamIO` | Encapsuling `php stream resource` of Process   |
|    `StreamReader` | Generator for line。read line from `StreamIO`。 |

## Notice 

#### Notice 1 LINUX PIPE_BUFF

This packaged is interfered with `PIPE_BUFF / PIPE_SIZE` of Linux kernel.

Without reading STDOUT and STDOUT , and too many writing , Linux PIPE buff get stuck. Then, Process can't write , 
Linux will make sleep the process . Kernel has `PIPE_SIZE=65,536` bytes.If stdout
 keep 64kb without reading, then the process will be stopped. 
To prevent this blocking, Streams must be read proper timing or specify output file.
Like command that `FFMpeg`, `ImageMagick` is used in `proc_open`, It will write large size byte onto
STDOUT, These command will be blocked and stopped.


This package is on purpose designed not to prevent blocking.

This package **intended** very consciously will be **blocked** and stopped. 

```php
<?php
// stopped by IO Blocking at stdout.
$arg = ExecArgStruct('ffmpeg -i input.mp4 -s 1280x720 -f mp4 pipe:1');
$ffmpeg = new ProcessExecutor( $arg );
$ffmpeg->start();// blocked

// not stopped,
// stdout will be written to FILE(output.bin) 
$arg = ExecArgStruct('ffmpeg -i input.mp4 -s 1280x720 -f mp4 pipe:1');
$arg->setStdout(fopen('output.bin','w'));
$ffmpeg = new ProcessExecutor( $arg );
$ffmpeg->start();// not blocked
```

#### Notice 2: semaphore

Semaphore and SharedMemory is used in `ForkedExecutor` in daemonize. 

Semaphore and SharedMemory will cause trouble, shortage of size and fails to allocate memory, without deallocating.

After interrupted of CTRL-C, Check Semaphore and SharedMemory is released.


Use these command carefully to manage. ( especially macOS, has few memory for shared memory)
```shell
ipcs -a
ipcmr -m $id
## 例
ipcs -a | \grep `whoami` | awk '{print $2}' | xargs  -I@ ipcrm -m @
ipcs -a | \grep `whoami` | awk '{print $2}' | xargs  -I@ ipcrm -s @
```

#### Notice 3 :  pcntl_async_signals

`pcntl_async_signals` called in advance, to signal detection.

POSIX signal detection , write one line (pcntl_async_signals). Without `pcntl_async_signals` result in no POSIX
signal. (see :[PHP and SIGNALS,in background](https://www.slideshare.net/do_aki/20171008-signal-onphp) )

```
pcntl_async_signals( true )
```

In `ProcessExecutor` this line implicitly, but `proc_open` and `ProcOpen`(wrapper) needs explicitly calling. 


## Test 

run PHPUnit for testing this.  

```shell
git clone https://github.com/takuya/php-process-exec
cd php-process-exec
composer install 
vendor/bin/phpunit 
vendor/bin/phpunit --filter ProcOpenTest
```

## code coverage 

show Code coverage in phpunit

```shell
XDEBUG_MODE=debug,coverage vendor/bin/phpunit --coverage-html coverage
```

## TODO: 2024-05-27
- Buffering stdout,stdout after reached Linux PIPE_MAX 
- supporting TTY
  -  check by stream_isatty and can write Y/N.

## todo: 2024-09-15 
Buffering stdout,stdout after reached Linux PIPE_MAX

ffprobe raise trouble , so I need automated buffering.
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
