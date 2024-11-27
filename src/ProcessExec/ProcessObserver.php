<?php

namespace Takuya\ProcessExec;

use Takuya\Event\EventObserver;
use Takuya\ProcessExec\Traits\ObserveProcessStream;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessReady;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessStarted;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessRunning;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessSucceed;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessStopped;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessErrorOccurred;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessCanceled;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessFinished;
use Takuya\ProcessExec\ProcessEvents\Events\StdoutChanged;
use Takuya\ProcessExec\ProcessEvents\Events\StderrChanged;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessResumed;

class ProcessObserver extends EventObserver {
  
  use ObserveProcessStream;
  
  protected $events = [
    ProcessCanceled::class,
    ProcessErrorOccurred::class,
    ProcessFinished::class,
    ProcessReady::class,
    ProcessRunning::class,
    ProcessStarted::class,
    ProcessStopped::class,
    ProcessResumed::class,
    ProcessSucceed::class,
    StdoutChanged::class,
    StderrChanged::class,
  ];
}