<?php


use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\ExecArgStruct;

require __DIR__.'/../vendor/autoload.php';

$arg = new ExecArgStruct();
$arg->setCmd( ['php'] );
$src =<<<'EOS'
<?php
$fp=fopen('php://stderr','a');
foreach(range(0,4) as $i){
  fprintf($fp,"%d\r",$i);
  fflush($fp);
}
EOS;
$arg->setInput( $src );
$executor = new ProcessExecutor( $arg );
$executor->onStdErr(fn ($line)=> print( $line.PHP_EOL) ,"\r");
$executor->start();
