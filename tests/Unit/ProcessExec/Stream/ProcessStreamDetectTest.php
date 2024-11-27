<?php

namespace Tests\Unit\ProcessExec\Stream;

use Tests\TestCase;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\ProcessObserver;
use Takuya\ProcessExec\ProcessEvents\ProcessEvent;
use Takuya\ProcessExec\ProcessEvents\Events\StdoutChanged;
use Takuya\ProcessExec\ProcessEvents\Events\StderrChanged;

class ProcessStreamDetectTest extends TestCase {
  
  public function test_stdout_update_detection () {
    $arg = new ExecArgStruct();
    $arg->setCmd( ['php'] );
    $src = <<<'EOS'
      <?php
        for($i=0;$i<10;$i++){
          echo 'Hello World'.PHP_EOL;
          usleep(1000*10);
        }
      EOS;
    $arg->setInput( $src );
    $executor = new ProcessExecutor( $arg );
    $executor->watch_interval = 0.01;
    $observer = new ProcessObserver();
    $cnt = 0;
    $observer->addEventListener( StdoutChanged::class, function( ProcessEvent $ev ) use ( &$cnt ) {
      $stdout = $ev->getExecutor()->getProcess()->stdout();
      $line = stream_get_line( $stdout, 1024, "\n" );
      if ( false !== $line ) {
        $this->assertEquals( "Hello World", $line );
      }
      $cnt++;
    } );
    $executor->addObserver( $observer );
    $executor->start();
    $this->assertGreaterThan( 1, $cnt );
  }
  
  public function test_stderr_update_detection () {
    $arg = new ExecArgStruct();
    $arg->setCmd( ['php'] );
    $src = <<<'EOS'
      <?php
      $fp = fopen("php://stderr",'w');
      for($i=1;$i<30;$i++){
        fwrite( $fp, str_repeat("=",$i).">\r");
        usleep(1000*10);
      }
      EOS;
    $arg->setInput( $src );
    $executor = new ProcessExecutor( $arg );
    $executor->watch_interval = 0.01;
    $observer = new ProcessObserver();
    $observer->addEventListener( StderrChanged::class, function( ProcessEvent $ev ) {
      $stderr = $ev->getExecutor()->getProcess()->stderr();
      $line = stream_get_line( $stderr, 1024, "\r" );
      $this->assertStringContainsString( "=>", $line );
    } );
    $executor->addObserver( $observer );
    $executor->start();
  }
}