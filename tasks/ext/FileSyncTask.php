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
 * The FileSyncTask class copies files either to or from a remote host, or locally 
 * on the current host. It allows rsync to transfer the differences between two 
 * sets of files across the network connection, using an efficient checksum-search 
 * algorithm.
 *
 * There are 4 different ways of using FileSyncTask:
 *
 *   1. For copying local files.
 *   2. For copying from the local machine to a remote machine using a remote 
 *      shell program as the transport (ssh).
 *   3. For copying from a remote machine to the local machine using a remote 
 *      shell program.
 *   4. For listing files on a remote machine.
 *
 * @author    Federico Cargnelutti <fedecarg@gmail.com>
 * @version   $Revision$
 * @package   phing.tasks.ext
 */
class FileSyncTask extends Task 
{
	/**
	 * Source directory.
	 * @var string
	 */
	protected $sourceDir;
	
	/**
	 * Destination directory.
	 * @var string
	 */
	protected $destinationDir;

	/**
	 * Remote host.
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
	 * Excluded patterns file.
	 * @var string
	 */
	protected $excludeFile;
	
	/**
	 * This option creates a backup so users can rollback to an existing restore 
	 * point. The remote directory is copied to a new directory specified by the 
	 * user. 
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
	 * Connection type.
	 * @var boolean
	 */
	protected $isRemoteConnection = false;
	
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
	 * Phing's main method. Wraps the executeCommand() method.
	 * 
	 * @return void
	 */
	public function main() 
	{
		$this->executeCommand();
	}
	
	/**
	 * Executes the rsync command and returns the exit code.
	 * 
	 * @return int Return code from execution.
	 * @throws BuildException
	 */
	public function executeCommand() 
	{		
		if ($this->sourceDir === null) {
			throw new BuildException('The "sourcedir" attribute is missing or undefined.');
		} else if ($this->destinationDir === null) {
			throw new BuildException('The "destinationdir" attribute is missing or undefined.');
		}
		
		if (strpos($this->destinationDir, '@')) {
			$this->setIsRemoteConnection(true);
		} else {
			if (! (is_dir($this->destinationDir) && is_readable($this->destinationDir))) {
				throw new BuildException("No such file or directory: " . $this->destinationDir);
			}
		}
		
		if (strpos($this->sourceDir, '@')) {
			if ($this->isRemoteConnection) {
				throw new BuildException('Cannot set both source and destination remote directories.');
			}
			$this->setIsRemoteConnection(true);
		} else {
			if (! (is_dir($this->sourceDir) && is_readable($this->sourceDir))) {
				throw new BuildException('No such file or directory: ' . $this->sourceDir);
			}
		}
		
		if ($this->backupDir !== null && $this->backupDir == $this->destinationDir) {
			throw new BuildException("Invalid backup directory: " . $this->backupDir);
		}
		
		$command = $this->getCommand();
		print $this->getInformation();
		
		$output = array();
		$return = null;
		exec($command, $output, $return);
		if ($return != 0) {
			$this->log('Task exited with code: ' . $return, Project::MSG_INFO);
			return false;
		} else {
			foreach ($output as $line) {
				print $line . "\r\n";
			}
		}
	}
	
	
	/**
	 * Returns the rsync command line options.
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
		} else {
			if ($this->delete === true) {
				$options .= ' --delete-after --ignore-errors --force';
			}
		}
		if ($this->backupDir !== null) {
			$options .= ' -b --backup-dir=' . $this->backupDir;
		}
		$this->setOptions($options);
		
		$options .= ' ' . $this->sourceDir . ' ' . $this->destinationDir;
		
		escapeshellcmd($options);
		$options .= ' 2>&1';
		
		return 'rsync ' . $options;
	}
	
	/**
	 * Provides information about the command line options before the transfer.
	 *
	 * @return string
	 */
	public function getInformation() 
	{
		$server = ($this->isRemoteConnection) ? 'remote' : 'local';
		$backupDir = ($this->backupDir !== null) ? $this->backupDir : '(none)';
		
		$excludePatterns = '(none)';
		if (file_exists($this->excludeFile)) {
			$excludePatterns = @file_get_contents($this->excludeFile);
		}
		
		$lf = "\r\n";
		$dlf = $lf . $lf;
		
		$info  = $lf;
		$info .= 'Execute Command'                          . $lf;
		$info .= '----------------------------------------' . $lf;
		$info .= 'rsync ' . $this->options                  . $dlf;
		$info .= 'Sync files to ' . $server . ' server'     . $lf;
		$info .= '----------------------------------------' . $lf;
		$info .= 'Source:        ' . $this->sourceDir       . $lf;
		$info .= 'Destination:   ' . $this->destinationDir  . $lf;
		$info .= 'Backup:        ' . $backupDir             . $dlf;
		$info .= 'Exclude patterns'                         . $lf;
		$info .= '----------------------------------------' . $lf;
		$info .= $excludePatterns                           . $lf;

		return $info;
	}
	
	/**
	 * Sets the self::$isRemoteConnection property.
	 *
	 * @param boolean $isRemote
	 * @return void
	 */
	protected function setIsRemoteConnection($isRemote)
	{		
		$this->isRemoteConnection = $isRemote;
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
	 * Sets the destination directory. If the option remotehost is not included 
	 * in the build.xml file, rsync will point to a local directory instead. 
	 * 
	 * @param string $dir
	 */
	public function setDestinationDir($dir) 
	{
		$this->destinationDir = $dir;
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
	 * Makes backups into hierarchy based in $dir.
	 * 
	 * @param string dir
	 */
	public function setBackupDir($dir) 
	{
		$this->backupDir = $dir;	
	}
}
