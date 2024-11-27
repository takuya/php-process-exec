<?php

namespace Takuya\ProcessExec\Traits;


use Takuya\ProcessExec\ProcessEvents\Events\ProcessResumed;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessStopped;

trait ProcessSuspend {
  
  public function suspend () {
    return $this->proc->suspend();
  }
  
  public function resume () {
    $ret= $this->proc->resume();
    while ($this->isSuspended(true)){
      usleep(1);
    }
    $this->fireEvent( ProcessResumed::class );
    return $ret;
  }
  
  public function isSuspended ($use_ps_cmd =false): bool {
    return $this->proc->isSuspended($use_ps_cmd);
  }
}