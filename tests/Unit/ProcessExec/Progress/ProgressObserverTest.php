<?php

namespace Tests\Unit\ProcessExec\Progress;

use Tests\TestCase;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessExecutor;

class ProgressObserverTest extends TestCase {
  
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
  
  public function test_stdin_on_progress_monitor () {
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
    $struct->setInput( $input );
    $proc = new ProcessExecutor( $struct );
    $proc->onInputProgress( function( $percent ) {
      $this->assertGreaterThanOrEqual( 0, $percent );
      $this->assertLessThanOrEqual( 100, $percent );
    } );
    $proc->start();
  }
  
  protected function tearDown (): void {
    parent::tearDown();
    @unlink( $this->temp_file_path );
  }
}