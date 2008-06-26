<?php
/*
 * $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information please see
 * <http://phing.info>.
 */

require_once "phing/Task.php";

/**
 * The SyncTask class copies files either to or from a remote host, or locally 
 * on the current host. It allows rsync to transfer the differences between two 
 * sets of files across the network connection, using an efficient checksum-search 
 * algorithm.
 *
 * @author    Federico Cargnelutti <fedecarg@gmail.com>
 * @author    Hans Lellelid <hans@xmpl.org> (Phing)
 * @version   $Revision$
 * @package   phing.tasks.ext
 */
class SyncTask extends Task 
{
	/**
	 * Source directory.
	 * @var string
	 */
	protected $sourceDir;
	
	/**
	 * Connection type.
	 * @var boolean
	 */
	protected $isRemoteConnection = false;
	
	/**
	 * Remote directory.
	 * @var string
	 */
	protected $remoteDir;

	/**
	 * Connection host name.
	 * @var string
	 */
	protected $remoteHost;
	
	/**
	 * Rsync auth username.
	 * @var string
	 */
	protected $remoteUser;
	
	/**
	 * Rsync auth password. 
	 * @var string
	 */
	protected $remotePass;
	
	/**
	 * Remote shell.
	 * @var string
	 */
	protected $remoteShell;
	
	/**
	 * Excluded patterns.
	 * @var string
	 */
	protected $excludeFile;
	
	/**
	 * This option cerates a restore point so users can rollback to an existing 
	 * restore point. The remote directory is copied to a new directory specified 
	 * by the user. 
	 * 
	 * @var string
	 */
	protected $backupDir;
	
	/**
	 * Command options.
	 * @param string
	 */
	protected $options;
	
	/**
	 * This option increases the amount of information you are given during the
	 * transfer. The verbose option set to true will give you information about 
	 * what files are being transferred and a brief summary at the end.
	 * 
	 * @var boolean
	 */
	protected $verbose = true;
	
	/**
	 * This option will cause the source files to be listed instead of 
	 * transferred.
	 * 
	 * @var boolean
	 */
	protected $listOnly = false;
	
	/**
	 * This option deletes files that don't exist on sender.
	 * 
	 * @var boolean
	 */
	protected $delete = false;
	
	/**
	 * Phing's main method. Wraps the execute() method.
	 * 
	 * @return void
	 */
	public function main() 
	{
		$this->setIsRemoteConnection();
		$this->execute();
	}
	
	/**
	 * Executes the rsync program and returns the exit code.
	 * 
	 * @return int Return code from execution.
	 * @throws BuildException
	 */
	public function execute() 
	{		
		if ($this->sourceDir === null) {
			throw new BuildException('The sourcedir option is missing or undefined.');
		}
		if (! (is_dir($this->sourceDir) && is_readable($this->sourceDir))) {
			throw new BuildException("Can't chdir to: " . $this->sourceDir);
		} else if (! $this->isRemoteConnection) {
			if (! (is_dir($this->remoteDir) && is_readable($this->remoteDir))) {
				throw new BuildException("No such file or directory: " . $this->remoteDir);
			}
		}
		if ($this->backupDir !== null && $this->backupDir == $this->remoteDir) {
			throw new BuildException("Invalid backup directory: " . $this->backupDir);
		}
		
		@chdir($this->sourceDir);
		
		$command = $this->getCommand();
		print $this->getInformation($command);
		
		$output = array();
		$return = null;
		exec($command, $output, $return);
		
		foreach ($output as $line) {
			print $line . "\r\n";
		}

		if ($return != 0) {
			throw new BuildException('Task exited with code: ' . $return);
		}

		return $return;
	}
	
	/**
	 * Returns the rsync command and its command line options.
	 *
	 * @return string
	 */
	public function getCommand()
	{
		$options = '-raz';
		if ($this->options !== null) {
			$options = $this->options;
		}
		if ($this->verbose === true) { 
			$options .= 'v';
		}
		if ($this->remoteShell !== null) {
			$options .= ' -e ' . $this->remoteShell;
		}
		if ($this->listOnly === true) {
			$options .= ' --list-only';
		}
		if ($this->delete === true) {
			$options .= ' --delete';
		}
		if ($this->backupDir !== null) {
			$options .= ' -b --backup-dir=' . $this->backupDir;
		}
		
		$options .= ' --progress';
		$this->setOptions($options);
		
		if ($this->isRemoteConnection) {
			$options .= ' ' . $this->sourceDir;
			$options .= ' ' . $this->remoteUser.'@'.$this->remoteHost.':'.$this->remoteDir;
		} else {
			$options .= $this->sourceDir . ' ' . $this->remoteDir;
		} 
		
		escapeshellcmd($options);
		$options .= ' 2>&1';
		
		return 'rsync ' . $options;
	}
	
	/**
	 * Sets the remote self::$isRemoteConnection to true if the remotehost 
	 * option is defined.
	 *
	 * @param null $phing Required by Phing when using setter methods.
	 * @throws BuildException
	 */
	public function setIsRemoteConnection($phing=null)
	{
		if ($this->remoteHost !== null) {
			if ($this->remoteDir === null) {
				throw new BuildException('The remotedir option is missing or undefined.');
			} else if ($this->remoteUser === null) {
				throw new BuildException('The remoteuser option is missing or undefined.');
			} else if ($this->remotePass !== null && $this->remoteShell !== null) {
				throw new BuildException('The remotepass option is only useful when accessing an rsync daemon.');
			}
			$this->isRemoteConnection = true;
		} else {
			$this->isRemoteConnection = false;
		}
	}

	/**
	 * Sets the source directory.
	 * 
	 * @param string $dir
	 */
	public function setSourceDir($dir) 
	{
		$this->sourceDir = $dir;
	}
	
	/**
	 * Sets the command options.
	 * 
	 * @param string $options
	 */
	public function setOptions($options) 
	{
		$this->options = $options;
	}
	
	/**
	 * Sets the remote directory. If the option remotehost is not included in 
	 * the build.xml file, rsync will look for a local directory instead. 
	 * 
	 * @param string $dir
	 */
	public function setRemoteDir($dir) 
	{
		$this->remoteDir = $dir;
	}

	/**
	 * Sets the remote host.
	 * 
	 * @param string $host
	 */
	public function setRemoteHost($host) 
	{
		$this->remoteHost = $host;
	}
	
	/**
	 * Specifies the user to log in as on the remote machine. This also may be 
	 * specified in the properties file.
	 * 
	 * @param string $user
	 */
	public function setRemoteUser($user) 
	{
		$this->remoteUser = $user;
	}
	
	/**
	 * This option allows you to provide a password for accessing a remote rsync 
	 * daemon. Note that this option is only useful when accessing an rsync daemon 
	 * using the built in transport, not when using a remote shell as the transport. 
	 * 
	 * @param string $pass
	 */
	public function setRemotePass($pass) 
	{
		$this->remotePass = $pass;
	}
	
	/**
	 * Allows the user to choose an alternative remote shell program to use for 
	 * communication between the local and remote copies of rsync. Typically, 
	 * rsync is configured to use ssh by default, but you may prefer to use rsh 
	 * on a local network.
	 * 
	 * @param string $shell
	 */
	public function setRemoteShell($shell) 
	{
		$this->remoteShell = $file;
	}

	/**
	 * Increases the amount of information you are given during the
	 * transfer. By default, rsync works silently. A single -v will give you 
	 * information about what files are being transferred and a brief summary at 
	 * the end.
	 * 
	 * @param boolean $verbose
	 */
	public function setVerbose($verbose) 
	{
		$this->verbose = (bool) $verbose;
	}
	
	/**
	 * Causes the source files to be listed instead of transferred.
	 * 
	 * @param boolean $listOnly
	 */
	public function setListOnly($listOnly) 
	{
		$this->listOnly = (bool) $listOnly;
	}
	
	/**
	 * Tells rsync to delete extraneous files from the receiving side, but only 
	 * for the directories that are being synchronized. Files that are excluded 
	 * from transfer are also excluded from being deleted.
	 * 
	 * @param boolean $delete
	 */
	public function setDelete($delete) 
	{
		$this->delete = (bool) $delete;	
	}
	
	/**
	 * Exclude files matching patterns from $file, Blank lines in $file and 
	 * lines starting with ';' or '#' are ignored.
	 * 
	 * @param string $file
	 */
	public function setExcludeFile($file) 
	{
		$this->excludeFile = $file;	
	}
	
	/**
	 * Makes backups into hierarchy based in self::$backupDir
	 * 
	 * @param string dir
	 */
	public function setBackupDir($dir) 
	{
		$this->backupDir = $dir;	
	}
	
	/**
	 * Returns information about the transfer.
	 *
	 * @param string $command
	 * @return string
	 */
	public function getInformation() 
	{
		if ($this->isRemoteConnection) {
			$server = 'remote';
			$destinationDir = $this->remoteUser.'@'.$this->remoteHost.':'.$this->remoteDir;
		} else {
			$server = 'local';
			$destinationDir = $this->remoteDir;
		}
		
		$backupDir = '(none)';
		if ($this->backupDir !== null) {
			$backupDir = $this->backupDir;
		}
		
		$excludePatterns = '(none)';
		if (file_exists($this->excludeFile)) {
			$excludePatterns = @file_get_contents($this->excludeFile);
		}
		
		$lf = "\r\n";
		$dlf = $lf . $lf;
		
		$info  = 'Execute Command'                          . $lf;
		$info .= '----------------------------------------' . $lf;
		$info .= 'rsync ' . $this->options                  . $dlf;
		$info .= 'Sync files to ' . $server . ' server'     . $lf;
		$info .= '----------------------------------------' . $lf;
		$info .= 'Source:        ' . $this->sourceDir       . $lf;
		$info .= 'Destination:   ' . $destinationDir        . $lf;
		$info .= 'Backup:        ' . $backupDir             . $dlf;
		$info .= 'Exclude patterns'                         . $lf;
		$info .= '----------------------------------------' . $lf;
		$info .= $excludePatterns                           . $dlf;

		return $info;
	}
}
