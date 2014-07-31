<?php
/*
 * This file is part of the Magallanes package.
*
* (c) Andrés Montañez <andres@andresmontanez.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Mage\Task\BuiltIn\Deployment\Strategy;

use Mage\Task\BuiltIn\Deployment\Strategy\BaseStrategyTaskAbstract;
use Mage\Task\Releases\IsReleaseAware;

/**
 * Task for Sync the Local Code to the Remote Hosts via Tar GZ
 *
 * @author Andrés Montañez <andres@andresmontanez.com>
 */
class TarGzTask extends BaseStrategyTaskAbstract implements IsReleaseAware
{
	/**
	 * (non-PHPdoc)
	 * @see \Mage\Task\AbstractTask::getName()
	 */
    public function getName()
    {
        if ($this->getConfig()->release('enabled', false) == true) {
            if ($this->getConfig()->getParameter('overrideRelease', false) == true) {
                return 'Deploy via TarGz (with Releases override) [built-in]';
            } else {
                return 'Deploy via TarGz (with Releases) [built-in]';
            }
        } else {
                return 'Deploy via TarGz [built-in]';
        }
    }

    /**
     * Syncs the Local Code to the Remote Host
     * @see \Mage\Task\AbstractTask::run()
     */
    public function run()
    {
        $this->checkOverrideRelease();

        $excludes = $this->getExcludes();

        // If we are working with releases
        $deployToDirectory = $this->getConfig()->deployment('to');
        if ($this->getConfig()->release('enabled', false) == true) {
            $releasesDirectory = $this->getConfig()->release('directory', 'releases');

            $deployToDirectory = rtrim($this->getConfig()->deployment('to'), '/')
                               . '/' . $releasesDirectory
                               . '/' . $this->getConfig()->getReleaseId();
            $this->runCommandRemote('mkdir -p ' . $releasesDirectory . '/' . $this->getConfig()->getReleaseId());
        }

        // Create Tar Gz
        $localTarGz = tempnam(sys_get_temp_dir(), 'mage');
        $remoteTarGz = basename($localTarGz);
        $excludeCmd = '';
        foreach ($excludes as $excludeFile) {
            $excludeCmd .= ' --exclude=' . $excludeFile;
        }

        $command = 'tar cfz ' . $localTarGz . '.tar.gz ' . $excludeCmd . ' -C ' . $this->getConfig()->deployment('from') . ' .';
        $result = $this->runCommandLocal($command);

        // Copy Tar Gz  to Remote Host
        $command = 'scp ' . $this->getConfig()->getHostIdentityFileOption() . '-P ' . $this->getConfig()->getHostPort() . ' ' . $localTarGz . '.tar.gz '
                 . $this->getConfig()->deployment('user') . '@' . $this->getConfig()->getHostName() . ':' . $deployToDirectory;
        $result = $this->runCommandLocal($command) && $result;

        // Extract Tar Gz
        $this->getReleasesAwareCommand('tar xfz ' . $remoteTarGz . '.tar.gz');
        $result = $this->runCommandRemote($command) && $result;

        // Delete Tar Gz from Remote Host
        $this->getReleasesAwareCommand('rm ' . $remoteTarGz . '.tar.gz');
        $result = $this->runCommandRemote($command) && $result;

        // Delete Tar Gz from Local
        $command = 'rm ' . $localTarGz . ' ' . $localTarGz . '.tar.gz';
        $result = $this->runCommandLocal($command) && $result;

        $this->cleanUpReleases();

        return $result;
    }
}
