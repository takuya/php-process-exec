<?php

namespace Tests\Feature\ProcessExec\Pipe;

use Tests\TestCase;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\ProcessObserver;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessFinished;

class ProcessPipeReadStreamTest extends TestCase {
  
  public function test_pipe_p1_stderr_can_read_before_process_exit () {
    $arg1 = new ExecArgStruct( 'php' );
    $src = <<<'EOS'
    <?php
      $fp = fopen('php://stderr','a');
      foreach(range(0,9) as $i ){
        fwrite($fp,"from p1: Hello Error.\n");
        fflush($fp);
        usleep(10);
      }
      usleep(1000*100);
      // echo php source code to p2
      echo '<?'.'php '.'
      $fp = fopen("php://stderr","w");
      foreach(range(1,10) as $i ){
        fwrite($fp,"from p2: Hello Error.\n");
        fflush($fp);
        echo "p2:Hello world\n";
        flush();
        usleep(100);
      }
    ';
    EOS;
    $arg1->setInput( $src );
    $p1 = new ProcessExecutor( $arg1 );
    $p2 = $p1->pipe( new ExecArgStruct( 'php' ) );
    $running = [];
    $p1->onStderr( function() use ( &$p1, &$running ) { $running[] = $p1->getProcess()->info->running; } );
    $p1->watch_interval = 0;
    $p2->watch_interval = 0;
    $p2->start();
    $this->assertNotEquals( 0, array_product( $running ) );
    //
  }
  
  public function test_start_pipe_process_read_each_stderr_stdout () {
    $arg1 = new ExecArgStruct( 'php' );
    $src = <<<'EOS'
    <?php
      $fp = fopen('php://stderr','w');
      foreach(range(1,10) as $i ){
        fwrite($fp,"from p1: Hello Error.\n");
        fflush($fp);
        usleep(1000);
      }
      // echo php source code to p2
      echo '<?'.'php '.'
      $fp = fopen("php://stderr","w");
      foreach(range(1,10) as $i ){
        fwrite($fp,"from p2: Hello Error.\n");
        fflush($fp);
        echo "p2:Hello world\n";
        flush();
        usleep(1000);
      }
    ';
    EOS;
    $arg1->setInput( $src );
    $p1 = new ProcessExecutor( $arg1 );
    $h1 = new ProcessObserver();
    $p1_finish_called = 0;
    $p2_finish_called = 0;
    $p1_onerr_called = 0;
    $p2_onerr_called = 0;
    $h1->addEventListener( ProcessFinished::class, function() use ( &$p1_finish_called ) { $p1_finish_called++; } );
    $p1->addObserver( $h1 );
    $p1->onStderr( function() use ( &$p1_onerr_called ) { $p1_onerr_called++; } );
    $p2 = $p1->pipe( new ExecArgStruct( 'php' ) );
    $h2 = new ProcessObserver();
    $h2->addEventListener( ProcessFinished::class, function() use ( &$p2_finish_called ) { $p2_finish_called++; } );
    $p2->addObserver( $h2 );
    $p2->onStderr( function() use ( &$p2_onerr_called ) { $p2_onerr_called++; } );
    $p2->start();
    //
    $this->assertEquals( 1, $p1_finish_called );
    $this->assertEquals( 1, $p2_finish_called );
    $this->assertEquals( 10, $p1_onerr_called );
    $this->assertEquals( 10, $p2_onerr_called );
  }
}