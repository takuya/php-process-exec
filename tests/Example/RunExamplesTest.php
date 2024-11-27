<?php

namespace Tests\Example;

use Tests\TestCase;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessExecutor;

class RunExamplesTest extends TestCase {
  
  public function test_examples_are_runs_without_errors () {
    $list = array_map(
      'realpath',
      array_merge(
        glob( __DIR__.'/../../examples/*.php' ),
        glob( __DIR__.'/../../examples/*/*.php' ), ) );
    foreach ( $list as $f ) {
      $p = new ProcessExecutor( new ExecArgStruct( 'php', $f ) );
      $p->start();
      $this->assertEquals( 0, $p->getExitCode() );
      $this->assertEquals( "0\n1\n2\n3\n4\n", $p->getOutput() );
    }
  }
}