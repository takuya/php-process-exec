<?php

namespace Takuya\ProcessExec\Traits;

use Takuya\ProcOpen\ProcOpen;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\Observers\ProcessPipedObserver;
use Takuya\ProcessExec\ProcessEvents\Events\StdoutChanged;
use Takuya\ProcessExec\ProcessEvents\Events\StderrChanged;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessRunning;
use Takuya\ProcessExec\Exceptions\ProcessExecutorException;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessFinished;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessErrorOccurred;
use RuntimeException;
use InvalidArgumentException;

trait ProcessPipe {
  
  /**
   * @param ProcessExecutor|ExecArgStruct $arg
   * @return ProcessExecutor
   */
  public function pipe ( ProcessExecutor|ExecArgStruct $arg ): ProcessExecutor {
    // ここおかしい
    !$this->getProcess()?->info->running && $this->startSubProcess();
    $p2 = match ( get_class( $arg ) ) {
      ProcessExecutor::class => $arg,
      ExecArgStruct::class => $arg->setInput( $this->getProcess()->stdout() ) ?? new ProcessExecutor( $arg ),
      default => throw new InvalidArgumentException( 'argument $arg is not ProcessExecutor|ExecArgStruct' )
    };
    $p2->setInput( $this->getProcess()->stdout() );
    $p2->watch_interval = 0.01;
    $this->connect_process_event( $p2 );
    
    return $p2;
  }
  
  public function setInput ( $var ) {
    // もしかして使ってない？
    $this->struct->setInput( $var );
  }
  
  protected function connect_process_event ( $p2 ): void {
    $finished_event_is_fired = false;
    $p1_on_wait = function() use ( &$finished_event_is_fired ) {
      try {
        if ( $finished_event_is_fired ) {
          return;
        }
        if ( $this->getProcess()->info->running ) {
          $this->fireEvent( ProcessRunning::class );
          $this->fireStreamEvent( ProcOpen::STDOUT, StdoutChanged::class );
          $this->fireStreamEvent( ProcOpen::STDERR, StderrChanged::class );
        } else {
          $this->fireEvent( ProcessFinished::class );
          $finished_event_is_fired = true;
        }
      } catch (ProcessExecutorException|RuntimeException $exception) {
        $this->fireEvent( ProcessErrorOccurred::class );
        $this->last_exception = $exception;
        $this->throw_exception && throw $this->last_exception;
      }
    };
    $piped_observer = new ProcessPipedObserver();
    $piped_observer->connect_on_wait( $p2, $p1_on_wait );
  }
}