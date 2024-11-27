<?php

namespace Tests\Unit\ProcessExec;

use Tests\TestCase;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\ProcessObserver;
use Takuya\ProcessExec\ProcessEvents\ProcessEvent;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessFinished;

class ProcessExecutorTest extends TestCase {
  
  public function test_process_execute () {
    $arg = new ExecArgStruct();
    $arg->setCmd( ['php'] );
    $src = <<<'EOS'
      <?php echo 'Hello World';
      EOS;
    $arg->setInput( $src );
    $executor = new ProcessExecutor( $arg );
    $observer = new ProcessObserver();
    $observer->addEventListener( ProcessFinished::class,
      fn( ProcessEvent $ev ) => $this->assertEquals( 'Hello World', $ev->getExecutor()->getOutput() ) );
    $executor->addObserver( $observer );
    $executor->start();
  }
}