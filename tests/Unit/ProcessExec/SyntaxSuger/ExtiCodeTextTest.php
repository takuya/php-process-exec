<?php

namespace Tests\Unit\ProcessExec\SyntaxSuger;

use Tests\TestCase;
use Takuya\ProcessExec\Enum\ProcessExitCode;

class ExtiCodeTextTest extends TestCase {
  
  public function test_exit_code_text_on_unkonw_code () {
    $codes = array_keys( ProcessExitCode::$exitCodes );
    foreach ( range( 0, max( $codes ) ) as $i ) {
      if ( in_array( $i, $codes ) ) {
        $this->assertEquals( ProcessExitCode::$exitCodes[$i], ProcessExitCode::getExitCodeText( $i ) );
      } else {
        $ret = ProcessExitCode::getExitCodeText( $i );
        $this->assertMatchesRegularExpression( '/unknown/i', $ret );
      }
    }
  }
}