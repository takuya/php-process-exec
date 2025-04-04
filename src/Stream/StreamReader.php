<?php

namespace Takuya\Stream;

class StreamReader {
  
  public function __construct (
    protected StreamIO $s
  ) {
  }
  
  public function readAll ( $delim = "\n" ) {
    while ( ( $line = $this->readLine( $delim ) ) !== false ) {
      yield $line;
    }
  }
  
  public function readLine ( $delim = "\n" ) {
    if ( $this->readReady()
      && !feof( $this->s->getStream() )
      && false !== ( $line = stream_get_line( $this->s->getStream(), 1024, $delim ) ) ) {
      return $line;
    }
    return false;
  }
  
  public function readReady () {
    return $this->s->readReady();
  }
}