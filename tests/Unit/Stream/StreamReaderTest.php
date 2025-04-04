<?php

namespace Tests\Unit\Stream;

use Tests\TestCase;
use Takuya\Stream\StreamIO;
use Takuya\Stream\StreamReader;

class StreamReaderTest extends TestCase {
  
  public function test_stream_readline_base_usage () {
    $fp = fopen( "php://memory", 'a' );
    foreach ( range( 1, 10 ) as $i ) {
      fwrite( $fp, sprintf( "%05d: Hello World.\n", $i ) );
    }
    rewind( $fp );
    $sr = new StreamReader( new StreamIO( $fp ) );
    foreach ( $sr->readAll() as $line ) {
      $this->assertMatchesRegularExpression( '/^\d+:\sHello World.$/', $line );
    }
  }
  
  public function test_stream_readlines_has_blank () {
    $fp = fopen( "php://memory", 'a' );
    foreach ( range( 1, 10 ) as $i ) {
      fwrite( $fp, sprintf( "%05d: Hello World.\n\n\n", $i ) );
    }
    rewind( $fp );
    $sr = ( new StreamIO( $fp ) )->getReader();
    foreach ( $sr->readAll() as $idx => $line ) {
      if ( $idx%3 === 0 ) {
        $this->assertMatchesRegularExpression( '/^\d+:\sHello World.$/', $line );
      } else {
        $this->assertEquals( '', $line );
      }
    }
  }
  
  public function test_stream_read_with_line_feed () {
    $fp = fopen( "php://memory", 'a' );
    foreach ( range( 1, 10 ) as $i ) {
      fwrite( $fp, sprintf( "%s>: Hello World.\r", str_repeat( '=', $i ) ) );
    }
    rewind( $fp );
    $sr = new StreamReader( new StreamIO( $fp ) );
    foreach ( $sr->readAll( "\r" ) as $line ) {
      $this->assertMatchesRegularExpression( '/=+>:\sHello World.$/', $line );
    }
  }
  
  public function test_stream_read_with_carrige_return_and_line_feed () {
    $fp = fopen( "php://memory", 'a' );
    foreach ( range( 1, 10 ) as $i ) {
      fwrite( $fp, sprintf( "%s>: Hello World.\r\n", str_repeat( '=', $i ) ) );
    }
    rewind( $fp );
    $sr = new StreamReader( new StreamIO( $fp ) );
    foreach ( $sr->readAll( "\r\n" ) as $line ) {
      $this->assertMatchesRegularExpression( '/=+>:\sHello World.$/', $line );
    }
  }
  
  public function test_stream_readline_temp_file () {
    $fp = fopen( "php://temp", 'a' );
    foreach ( range( 1, 10 ) as $i ) {
      fwrite( $fp, sprintf( "%05d: Hello World.\n", $i ) );
    }
    rewind( $fp );
    $sr = new StreamReader( new StreamIO( $fp ) );
    foreach ( $sr->readAll() as $line ) {
      $this->assertMatchesRegularExpression( '/^\d+:\sHello World.$/', $line );
    }
  }
  public function test_stream_without_a_trailing_newline(){
    $fp = fopen( "php://memory", 'a' );
    foreach ( range( 1, 10 ) as $i ) {
      fwrite( $fp, sprintf( "%s>: Hello World.\r\n", str_repeat( '=', $i ) ) );
    }
    fwrite( $fp, sprintf( "%s>: EOF.", str_repeat( '=', ++$i ) ) );
    rewind( $fp );
    //
    $sr = new StreamReader( new StreamIO( $fp ) );
    $lines = iterator_to_array($sr->readAll());
    $last_line = $lines[sizeof($lines)-1];
    $this->assertMatchesRegularExpression( '/=+>:\sEOF.$/', $last_line );
    
  }
}