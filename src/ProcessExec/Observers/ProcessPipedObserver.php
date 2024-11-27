<?php

namespace Takuya\ProcessExec\Observers;

use Takuya\ProcessExec\ProcessObserver;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessRunning;

class ProcessPipedObserver extends ProcessObserver {
  
  public function connect_on_wait ( $p2, $callback ) {
    $this->connect_event( $p2, ProcessRunning::class, $callback );
  }
  
  protected function connect_event ( $p2, $event_class, callable $callback ) {
    $this->addEventListener( $event_class, $callback );
    $p2->addObserver( $this );
  }
  
}