<?php

namespace Tests\Feature;

use Tests\TestCase;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\ProcessObserver;

class ProcessExecutorReadStdoutTest extends TestCase {
  
  public function test_process_execute_and_read_one_line_stdout () {
    $arg = new ExecArgStruct();
    $arg->setCmd( ['php'] );
    $src = <<<'EOS'
      <?php
      $fp = fopen("php://stderr",'w');
      fwrite($fp,"START\n" );
      foreach(range(1,10) as $i){
        printf("%03d: Hello World\n",$i);
        flush();
        usleep(100);
      }
      fwrite($fp,"END\n" );
      EOS;
    $arg->setInput( $src );
    $executor = new ProcessExecutor( $arg );
    $executor->watch_interval = 0.001;
    $observer = new ProcessObserver();
    $cnt = 0;
    $observer->onStdOut( function( $line ) use ( &$cnt ) {
      $this->assertEquals( 1, substr_count( $line, "Hello World" ) );
      $cnt++;
    } );
    $executor->addObserver( $observer );
    $executor->start();
    $this->assertEquals( 10, $cnt );
  }
  
  public function test_process_execute_and_read_one_line_stderr () {
    $arg = new ExecArgStruct();
    $arg->setCmd( ['php'] );
    $src = <<<'EOS'
      <?php
      $fp = fopen("php://stderr",'w');
      echo "START\r";
      foreach(range(1,10) as $i){
        fwrite($fp,sprintf("%03d: Hello World\r",$i) );
        fflush($fp);
        usleep(100);
      }
      echo "END";
      EOS;
    $arg->setInput( $src );
    $executor = new ProcessExecutor( $arg );
    $executor->watch_interval = 0.001;
    $observer = new ProcessObserver();
    $cnt = 0;
    $observer->onStdErr( function( $line ) use ( &$cnt ) {
      $this->assertEquals( 1, substr_count( $line, "Hello World" ) );
      $cnt++;
    }, "\r" );
    $executor->addObserver( $observer );
    $executor->start();
    $this->assertEquals( 10, $cnt );
  }
  
  public function test_process_execute_and_line_by_line_check () {
    $arg = new ExecArgStruct();
    $arg->setCmd( ['php'] );
    $src = <<<'EOS'
      <?php
      $fp = fopen("php://stderr",'w');
      fwrite($fp,"START\n" );
      foreach(range(0,4) as $i){
        printf("%d\n",$i);
        flush();
        usleep(10000);
      }
      fwrite($fp,"END\n" );
      EOS;
    $arg->setInput( $src );
    $executor = new ProcessExecutor( $arg );
    $executor->watch_interval = 1;
    $observer = new ProcessObserver();
    $cnt = 0;
    $observer->onStdOut( function( $line ) use ( &$cnt ) {
      $this->assertEquals( $cnt, $line );
      $cnt++;
    } );
    $executor->addObserver( $observer );
    $executor->start();
    $this->assertEquals( 5, $cnt );
  }
}