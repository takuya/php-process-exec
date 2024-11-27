<?php

namespace Tests\Unit\ProcessExec\Progress;

use Tests\TestCase;
use Takuya\ProcOpen\ProcOpen;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\ProcessObserver;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessRunning;

/**
 *  ProcessRunning にリスナを登録して、stdinの読み込み進捗を調べて、擬似的なPVを作る例。
 */
class ProgressPipeViewerTest extends TestCase {
  
  protected $temp_file_path;
  protected $temp_size = 1000*10;
  
  public function setUp (): void {
    parent::setUp();
    $fp = tmpfile();
    $info = stream_get_meta_data( $fp );
    $this->temp_file_path = $info['uri'];
    $path = $this->temp_file_path;
    fclose( $fp );
    file_put_contents( $path, str_repeat( str_repeat( "a", 9 ).PHP_EOL, $this->temp_size/10 ) );
  }
  
  public function test_stdin_progress_monitor_vanilla () {
    $input = fopen( $this->temp_file_path, 'r' );
    $src = <<<'EOS'
    $fp=fopen("php://stdin",'r');
    while(!feof($fp)){
      echo fread($fp,10);
      usleep(100);
    }
    EOS;
    // Pv のかわりに input を監視してみたけど、
    // 転送レートを調整するために、watch intervalや freadでバイトを調整するのが大変すぎる。
    $struct = new ExecArgStruct( 'php', '-r', $src, );
    $proc = new ProcessExecutor( $struct );
    $proc->watch_interval = 0; // ProcOpen側のusleep(1000)だけにする。
    $obs = new ProcessObserver();
    $proc->addObserver( $obs );
    $progress = 0;
    $total = $this->temp_size;
    $obs->addEventListener( ProcessRunning::class, function( ProcessRunning $ev ) use ( $input, &$progress, $total ) {
      $proc = $ev->getExecutor()->getProcess();
      if ( false == is_resource( $proc->getFd( ProcOpen::STDIN ) ) ) {
        return;
      }
      if ( !feof( $input ) ) {
        fwrite( $proc->getFd( ProcOpen::STDIN ), fread( $input, 1024 ) );
        $progress = ftell( $input )/$total;
        $this->assertLessThanOrEqual( 1, $progress );
        $this->assertGreaterThanOrEqual( 0, $progress );
      } else {
        fclose( $proc->getFd( ProcOpen::STDIN ) );
      }
    } );
    $proc->start();
    $this->assertEquals( 1, $progress );
  }
  
  protected function tearDown (): void {
    parent::tearDown();
    @unlink( $this->temp_file_path );
  }
}