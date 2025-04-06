<?php

namespace Takuya\ProcessExec;

use Exception;
use Takuya\ProcOpen\ProcOpen;
use Takuya\ProcessExec\Traits\ProcessPipe;
use Takuya\ProcessExec\Traits\ProcessSuspend;
use Takuya\ProcessExec\Traits\ProgressiveInput;
use Takuya\ProcessExec\Traits\ProcessStreaming;
use Takuya\ProcessExec\Traits\ProcessEventEmitter;
use Takuya\ProcessExec\Traits\ProcessCompatSymfony;
use Takuya\ProcessExec\Exceptions\ProcessExitNonzero;
use Takuya\ProcessExec\Exceptions\ProcessExitSignaled;
use Takuya\ProcessExec\Traits\ProcessPosixSignalHandler;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessReady;
use Takuya\ProcessExec\Traits\ProcessExecutorWaitHandler;
use Takuya\ProcessExec\ProcessEvents\Events\StdoutChanged;
use Takuya\ProcessExec\ProcessEvents\Events\StderrChanged;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessRunning;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessStarted;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessSucceed;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessStopped;
use Takuya\ProcessExec\Exceptions\ProcessExecutorException;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessFinished;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessCanceled;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessErrorOccurred;
use RuntimeException;
use LogicException;

class ProcessExecutor {
  
  use ProcessExecutorWaitHandler;
  use ProcessEventEmitter;
  use ProcessPosixSignalHandler;
  use ProcessCompatSymfony;
  use ProcessPipe;
  use ProcessStreaming;
  use ProgressiveInput;
  use ProcessSuspend;
  
  /**
   * @var bool default is false. ( 'false' means, failed run is No Exception. )
   */
  public $throw_exception = false;
  /**
   * @var ProcOpen
   */
  protected $proc;
  /**
   * @var ExecArgStruct
   */
  protected $struct;
  /**
   * @var Exception
   */
  protected $last_exception;
  
  public function __construct ( ExecArgStruct $struct ) {
    $this->struct = $struct;
  }
  
  public function start () {
    $this->handleSubProcess();
  }
  
  protected function handleSubProcess () {
    $this->startSubProcess();
    $this->waitSubProcess();
  }
  
  protected function startSubProcess () {
    try {
      $proc = $this->proc = $this->prepare();
      $this->fireEvent( ProcessReady::class );
      $proc->start();
      $this->fireEvent( ProcessStarted::class );
    } catch (ProcessExecutorException|RuntimeException|LogicException $exception) {
      $this->last_exception = $exception;
      $this->fireEvent( ProcessErrorOccurred::class );
      throw $exception;
    }
  }
  
  protected function prepare (): ProcOpen {
    if ( $this->has_input_progreess_monitor() ) {
      $this->monitorInputProgress();
    }
    $proc = $this->struct->prepareProcess();
    $this->posix_ensure_signal_attach();
    
    return $proc;
  }
  protected function checkStream(){
    if ( $this->isSuspended() ) {
      return;
    }
    $interval= [intval($this->watch_interval),intval($this->watch_interval*1000*1000)];
    $this->fireStreamEvent( ProcOpen::STDOUT, StdoutChanged::class,...$interval);
    $this->fireStreamEvent( ProcOpen::STDERR, StderrChanged::class,...[0,200] );
  }
  
  protected function waitSubProcess () {
    try {
      $watcher = $this->watcher( function() {
        if ( $this->isSuspended() ) {
          $this->fireEvent( ProcessStopped::class );
          return;
        }
        $this->fireEvent( ProcessRunning::class );
        $this->checkStream();
      } );
      $this->proc->wait( $watcher );
      $this->assertProcessSuccess();
      $this->fireEvent( ProcessSucceed::class );
    } catch (ProcessExitSignaled $exception) {
      $this->fireEvent( ProcessCanceled::class );
    } catch (ProcessExecutorException|RuntimeException $exception) {
      $this->last_exception = $exception;
      $this->fireEvent( ProcessErrorOccurred::class );
    } catch (\Error $error) {
      throw $error;
    } finally {
      $this->proc->info->running && $this->stop();
      $this->fireEvent( ProcessFinished::class );
      $this->throw_exception && !empty( $this->last_exception ) && throw $this->last_exception;
    }
  }
  
  protected function assertProcessSuccess () {
    $this->proc->info->signaled && throw new ProcessExitSignaled( $this->proc->info->termsig );
    $this->proc->info->exitcode !== 0 && throw new ProcessExitNonzero( "ExitCode:".$this->proc->info->exitcode );
  }
  
  public function getSubProcessId (): ?int {
    return $this->proc->info->pid;
  }
  
  public function getLastException (): Exception {
    return $this->last_exception;
  }
  
  public function getProcess (): ?ProcOpen {
    return $this->proc;
  }
}