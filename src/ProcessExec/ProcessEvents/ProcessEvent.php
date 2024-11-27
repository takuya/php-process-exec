<?php

namespace Takuya\ProcessExec\ProcessEvents;

use Takuya\ProcessExec\ProcessExecutor;
use Takuya\Event\GenericEvent;

abstract class ProcessEvent extends GenericEvent {
  
  public function __construct ( ProcessExecutor $executor ) {
    parent::__construct( $executor );
  }
  
  /**
   * @return ProcessExecutor
   */
  public function getExecutor (): ProcessExecutor {
    return $this->getEventSource();
  }
}