<?php

namespace Tests\Unit\ProcessExec;

use Tests\TestCase;
use InvalidArgumentException;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcOpen\Exceptions\InvalidStreamException;
use Takuya\ProcessExec\Exceptions\HasNotStartedException;

class ExecArgStructTest extends TestCase {
  
  public function test_command_args_struct_setcmd () {
    $args = new ExecArgStruct( 'php' );
    $this->assertEquals( ['php'], $args->getCmd() );
    //
    $args = new ExecArgStruct( 'php', 'index.php' );
    $this->assertEquals( ['php', 'index.php'], $args->getCmd() );
  }
  
  public function test_command_args_struct_set_stream_stdout () {
    $args = new ExecArgStruct( 'php', '-i' );
    $args->setStdout( $out = fopen( "php://temp", 'w' ) );
    $p = new ProcessExecutor( $args );
    $p->start();
    $this->assertStringContainsString( "_SERVER['SHELL']", $p->getOutput() );
    rewind( $out );
    $this->assertEquals( stream_get_contents( $out ), $p->getOutput() );
  }
  
  public function test_command_args_struct_set_stream_error () {
    $args = new ExecArgStruct( 'php' );
    $args->setInput(
      <<<EOS
    <?php
    file_put_contents("php://stderr",'Hello stderr');
    EOS
    );
    $args->setStderr( $err = fopen( "php://temp", 'w' ) );
    $p = new ProcessExecutor( $args );
    $p->start();
    $this->assertEquals( "Hello stderr", $p->getErrout() );
    rewind( $err );
    $this->assertEquals( stream_get_contents( $err ), $p->getErrout() );
  }
  
  public function test_command_args_struct_set_stream_but_has_not_started () {
    $this->expectException( HasNotStartedException::class );
    $args = new ExecArgStruct( 'php' );
    $args->setInput(
      <<<EOS
    <?php
    file_put_contents("php://stderr",'Hello stderr');
    EOS
    );
    $args->setStderr( $err = fopen( "php://temp", 'w' ) );
    $p = new ProcessExecutor( $args );
    //$p->start(); omit start intentionally
    $p->getErrout();
  }
  
  public function test_command_args_struct_setstderr_to_no_stream () {
    $this->expectException( InvalidStreamException::class );
    $args = new ExecArgStruct( 'php' );
    $args->setStderr( 'dummy' );
    $p = new ProcessExecutor( $args );
    $p->start();
  }
  
  public function test_command_args_struct_array_of_array () {
    $cmd = ['php', '-i'];
    $args = new ExecArgStruct( $cmd );
    $this->assertEquals( $cmd, $args->getCmd() );
    $args = new ExecArgStruct( ...$cmd );
    $this->assertEquals( $cmd, $args->getCmd() );
  }
  
  public function test_command_args_struct_string_to_array () {
    $args = new ExecArgStruct( 'php -i' );
    $this->assertEquals( ['php', '-i'], $args->getCmd() );
    $args = new ExecArgStruct( 'php    -i  ' );
    $this->assertEquals( ['php', '-i'], $args->getCmd() );
    $args = new ExecArgStruct( 'php    -i  "a.php" ' );
    $this->assertEquals( ['php', '-i', 'a.php'], $args->getCmd() );
    $args = new ExecArgStruct( 'php  -lt  -i  "a-spaced  name.php" ' );
    $this->assertEquals( ['php', '-lt', '-i', 'a-spaced  name.php'], $args->getCmd() );
    $args = new ExecArgStruct( 'php    -i  "a.php"  "> b" ' );
    $this->assertEquals( ['php', '-i', 'a.php', '> b'], $args->getCmd() );
    $args = new ExecArgStruct( "php    -i  'a.php '   " );
    $this->assertEquals( ['php', '-i', 'a.php '], $args->getCmd() );
  }
  
  public function test_command_args_filename_quoted () {
    $args = new ExecArgStruct( 'touch "/tmp/a-spaced  name.php" ' );
    $proc = new ProcessExecutor( $args );
    $proc->start();
    $this->assertFileExists( "/tmp/a-spaced  name.php" );
    unlink( "/tmp/a-spaced  name.php" );
  }
  
  public function test_command_args_strunct_raise_error () {
    $this->expectException( InvalidArgumentException::class );
    new ExecArgStruct( "php -i > a.txt   " );
  }
}