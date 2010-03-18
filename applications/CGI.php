<?php
return new CGI;
class CGI extends AppInstance
{
 public $errlogfile;
 public $errlogfp;
 public $binPath = 'php-cgi';
 public $cwd = NULL;
 public $readPacketSize = 4096;
 public $binAliases = array(
  'php5' => '/usr/local/php/bin/php-cgi',
  'php6' => '/usr/local/php6/bin/php-cgi',
  'perl' => '/usr/bin/perl',
  'python' => '/usr/local/bin/python',
  'ruby' => '/usr/local/bin/ruby',
 );
 public $chroot = '/';
 public function onReady()
 {
  $this->errlogfile = dirname(__FILE__).'/cgi-error.log';
  if (!isset(Daemon::$settings[$k = 'modcgiallowoverridebinpath'])) {Daemon::$settings[$k] = TRUE;}
  if (!isset(Daemon::$settings[$k = 'modcgiallowoverridecwd'])) {Daemon::$settings[$k] = TRUE;}
  if (!isset(Daemon::$settings[$k = 'modcgiallowoverridechroot'])) {Daemon::$settings[$k] = TRUE;}
  if (!isset(Daemon::$settings[$k = 'modcgiallowoverrideuser'])) {Daemon::$settings[$k] = TRUE;}
  if (!isset(Daemon::$settings[$k = 'modcgiallowoverridegroup'])) {Daemon::$settings[$k] = TRUE;}
  if (!isset(Daemon::$settings[$k = 'modcgioutputerrors'])) {Daemon::$settings[$k] = TRUE;}
 }
 public function beginRequest($req,$upstream) {return new CGIRequest($this,$upstream,$req);}
}
class CGIRequest extends Request
{
 public $terminateOnAbort = FALSE;
 public $proc;
 public function init()
 {
  $this->header('Content-Type: text/html'); // default header.
  $this->proc = new AsyncProcess;
  $this->proc->readPacketSize = $this->appInstance->readPacketSize;
  $this->proc->onReadData(array($this,'onReadData'));
  $this->proc->onWrite(array($this,'onWrite'));
  $this->proc->outputErrors = Daemon::$settings['modcgioutputerrors'];
  $this->proc->binPath = $this->appInstance->binPath;
  $this->proc->chroot = $this->appInstance->chroot;
  if (isset($this->attrs->server['BINPATH']))
  {
   if (isset($this->appInstance->binAliases[$this->attrs->server['BINPATH']])) {$this->proc->binPath = $this->appInstance->binAliases[$this->attrs->server['BINPATH']];}
   elseif (Daemon::$settings['modcgiallowoverridebinpath']) {$this->proc->binPath = $this->attrs->server['BINPATH'];}
  }
  if (isset($this->attrs->server['CHROOT']) && Daemon::$settings['modcgiallowoverridechroot']) {$this->proc->chroot = $this->attrs->server['CHROOT'];}
  if (isset($this->attrs->server['SETUSER']) && Daemon::$settings['modcgiallowoverrideuser']) {$this->proc->setUser = $this->attrs->server['SETUSER'];}
  if (isset($this->attrs->server['SETGROUP']) && Daemon::$settings['modcgiallowoverridegroup']) {$this->proc->setGroup = $this->attrs->server['SETGROUP'];}
  if (isset($this->attrs->server['CWD']) && Daemon::$settings['modcgiallowoverridecwd']) {$this->proc->cwd = $this->attrs->server['CWD'];}
  elseif ($this->appInstance->cwd !== NULL) {$this->proc->cwd = $this->appInstance->cwd;}
  else {$this->proc->cwd = dirname($this->attrs->server['SCRIPT_FILENAME']);}
  $this->proc->setArgs(array($this->attrs->server['SCRIPT_FILENAME']));
  $this->proc->setEnv($this->attrs->server);
  $this->proc->execute();
 }
 public function run()
 {
  if (!$this->proc)
  {
   $this->out('Couldn\'t execute CGI proccess.');
   return 1;
  }
  if (!$this->proc->eof()) {$this->sleep();}
  return 1;
 }
 public function onAbort()
 {
  if ($this->terminateOnAbort && $this->stream) {$this->stream->close();}
 }
 public function onFinish()
 {
 }
 public function onWrite($process)
 {
  if ($this->attrs->stdin_done && ($this->proc->writeState === FALSE)) {$this->proc->closeWrite();}
 }
 public function onReadData($process,$data)
 {
  $this->combinedOut($data);
 }
 public function stdin($c)
 {
  if ($c === '') {return $this->onWrite($this->proc);}
  $this->proc->write($c);
 }
}