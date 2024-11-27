<?php

namespace Tests\Unit\ProcessExec\Progress\SyntaxSuger;

use Tests\TestCase;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessExecutor;

class ProcessExecutorWatchStOutStdErrTest extends TestCase {
  
  public function test_process_executor_on_std_out_changed () {
    $arg = new ExecArgStruct( 'php' );
    $src = <<<'EOS'
      <?php echo 'Hello World';
      EOS;
    $arg->setInput( $src );
    $executor = new ProcessExecutor( $arg );
    $executor->onStdout( fn( $line ) => $this->assertEquals( 'Hello World', $line ) );
    $executor->start();
  }
  
  public function test_process_executor_on_std_err_changed () {
    $arg = new ExecArgStruct( 'php' );
    $src = <<<'EOS'
      <?php
      file_put_contents('php://stderr','Hello Error');
      EOS;
    $arg->setInput( $src );
    $executor = new ProcessExecutor( $arg );
    $executor->onStderr( fn( $line ) => $this->assertEquals( 'Hello Error', $line ) );
    $executor->start();
  }
}