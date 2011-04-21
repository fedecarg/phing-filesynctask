<?php
require_once 'PHPUnit/Framework.php';

require_once '/usr/share/php/phing/Task.php';
require_once '/var/www/phing-filesynctask/tasks/ext/FileSyncTask.php';

/**
 * Test class for FileSyncTask.
 *
 * I will just test if adding alternate ssh port support works fine and dosen't harm existing features
 */
class FileSyncTaskTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var FileSyncTask
     */
    protected $object;

    protected function setUp()
    {
        $this->object = new FileSyncTask;

        $this->object->setDestinationDir('anis@ajaxray.com:/var/www');
        $this->object->setSourceDir('/var/www/fileSyncTask');
    }

    public function testGetCommandWorksWithDefaultSshOptions()
    {
        $expectedCommand = 'rsync -raz /var/www/fileSyncTask anis@ajaxray.com:/var/www 2>&1';
        $this->assertEquals($expectedCommand, $this->object->getCommand());
    }

    public function testGetCommandWorksWithIdentityFile()
    {
        //$expectedCommand = 'rsync -raz /var/www/fileSyncTask anis@ajaxray.com:/var/www 2>&1';
        $expectedCommand = 'rsync -raz -e "ssh -i /path/to/file.ext" /var/www/fileSyncTask anis@ajaxray.com:/var/www 2>&1';

        $this->object->setIdentityFile('/path/to/file.ext');
        $this->assertEquals($expectedCommand, $this->object->getCommand());
    }

    public function testGetCommandWorksWithAlternateSshPort()
    {
        $expectedCommand = 'rsync -raz -e "ssh -p 1000" /var/www/fileSyncTask anis@ajaxray.com:/var/www 2>&1';

        $this->object->setSshPort(1000);
        $this->assertEquals($expectedCommand, $this->object->getCommand());
    }

    public function testGetCommandWorksWithAlternateSshPortAndIdentityFile()
    {
        $expectedCommand = 'rsync -raz -e "ssh -p 1000 -i /path/to/file.ext" /var/www/fileSyncTask anis@ajaxray.com:/var/www 2>&1';

        $this->object->setSshPort(1000);
        $this->object->setIdentityFile('/path/to/file.ext');
        $this->assertEquals($expectedCommand, $this->object->getCommand());
    }

}
