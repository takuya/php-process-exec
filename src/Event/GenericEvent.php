<?php

namespace Takuya\Event;

/**
 *
 */
class GenericEvent {
  
  /**
   * @var object EventEmitter
   */
  protected $eventSource;
  
  /**
   * @param $object
   */
  public function __construct ( $object ) {
    $this->eventSource = $object;
  }
  
  /**
   * @return object
   */
  public function getEventSource (): object {
    return $this->eventSource;
  }
  
}