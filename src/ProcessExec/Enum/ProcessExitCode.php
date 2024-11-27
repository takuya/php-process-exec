<?php

namespace Takuya\ProcessExec\Enum;

use Takuya\ProcessExec\Traits\ExitCodes;

class ProcessExitCode {
  use ExitCodes;
  
  public static function getExitCodeText ( $code ) {
    return self::$exitCodes[$code] ?? "Unknown ExitCode({$code})";
  }
}