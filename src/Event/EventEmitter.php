<?php

namespace Takuya\Event;

/**
 *
 */
trait EventEmitter {
  /** @var EventObserver[] */
  protected $observers = [];
  
  /**
   * @param EventObserver $observer
   */
  public function addObserver ( EventObserver $observer ) {
    $observer->setEventTarget( $this );
    $this->observers[] = $observer;
  }
  
  /**
   * @param mixed $ev Class Name of Event ( ex. SomeEventCreated::class )
   */
  public function fireEvent ( mixed $ev ) {
    $event = is_string( $ev ) ? new $ev( $this ) : $ev;
    $this->handleEvent( $event );
  }
  
  /**
   * @param GenericEvent $event
   */
  public function handleEvent ( GenericEvent $event ) {
    foreach ( $this->observers as $observer ) {
      $observer->notifyEvent( $event );
    }
  }
  
}