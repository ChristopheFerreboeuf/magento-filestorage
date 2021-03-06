<?php
require_once('vendor/autoload.php');
use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Util;

/**
 * Amazon S3 storage model
 *
 * @category    Uaudio
 * @package     Uaudio_Storage
 * @author      Universal Audio <web-dev@uaudio.com>
 */
class Uaudio_Storage_Model_Storage_S3 extends Uaudio_Storage_Model_Storage_Abstract {

    const STORAGE_MEDIA_ID = 2;

    /**
     * @var S3 access key
     */
    protected $_key;

    /**
     * @var S3 secret key
     */
    protected $_secret;

    /**
     * @var S3 region
     */
    protected $_region;

    /**
     * @var S3 bucket
     */
    protected $_bucket;

    /**
     * @var Cache Control
     */
    protected $_cachecontrol;

    /**
     * Initialize S3 settings
     *
     * @param array - allow settings override during synchronization
     */
    public function __construct($settings=[]) {
        $this->_key = isset($settings['s3_access_key']) ?  $settings['s3_access_key'] : Mage::getStoreConfig('system/media_storage_configuration/media_s3_access_key');
        $this->_secret = isset($settings['s3_secret_key']) ? $settings['s3_secret_key'] : Mage::getStoreConfig('system/media_storage_configuration/media_s3_secret_key');
        $this->_region = isset($settings['s3_region'])? $settings['s3_region'] : Mage::getStoreConfig('system/media_storage_configuration/media_s3_region');
        $this->_bucket = isset($settings['s3_bucket']) ? $settings['s3_bucket'] : Mage::getStoreConfig('system/media_storage_configuration/media_s3_bucket');
        $this->_folder = isset($settings['s3_folder']) ? $settings['s3_folder'] : Mage::getStoreConfig('system/media_storage_configuration/media_s3_folder');
        $this->_cachecontrol = isset($settings['s3_cachecontrol']) ? $settings['s3_cachecontrol'] : Mage::getStoreConfig('system/media_storage_configuration/media_s3_cachecontrol');
        if (!is_numeric($this->_cachecontrol)) unset($this->_cachecontrol);
        parent::__construct();
    }

    /**
     * Get the settings for this storage type
     *
     * @return array
     */
    public function settings() {
        return [
            's3_access_key' => 'Access Key',
            's3_secret_key' => 'Secret Key',
            's3_region'     => 'Region',
            's3_bucket'     => 'Bucket',
            's3_folder'     => 'Folder (optional)',
            's3_cachecontrol'     => 'Cache Control (optional)'
        ];
    }

    /**
     * Get flysystem adapter
     *
     * @return \League\Flysystem\AwsS3v2\AwsS3Adapter
     */
    protected function _getAdapter() {
        if(!$this->_adapter) {
            $config = [
                'region' => $this->_region,
                'version' => '2006-03-01',
            ];

            if($this->_key && $this->_secret) {
                $config['credentials']['key'] = $this->_key;
                $config['credentials']['secret'] = $this->_secret;
            }

            $client = S3Client::factory($config);
            $this->_adapter = new Uaudio_Storage_Model_League_AwsS3Adapter($client, $this->_bucket, $this->_folder, ['cachecontrol' => $this->_cachecontrol]);
        }
        return $this->_adapter;
    }

    /**
     * Get S3 client adapter
     *
     * @return
     */
    protected function _getClient() {
        return $this->_getAdapter()->getClient();
    }

    /**
     * Update file metadata
     *
     * @param string
     * @param array
     * @return self
     */
    public function updateMetadata($file, $metadata) {
        if($this->isInMedia($file) && $this->fileExists($file)) {
            $this->_getAdapter()->updateMetadata($this->getRelativeDestination($file), $metadata);
        }
        return $this;
    }

    /**
     * Create a presigned URL for S3
     *
     * @param string
     * @param int (expire link in X minutes)
     * @return string
     */
    public function getPresignedUrl($file, $expire=20) {
        $cmd = $this->_getClient()->getCommand('GetObject', [
            'Bucket' => $this->_bucket,
            'Key' => ($this->_folder ? $this->_folder.DS : '').$this->getRelativeDestination($file),
        ]);
        $request = $this->_getClient()->createPresignedRequest($cmd, "+$expire minutes");
        return (string)$request->getUri();
    }
}
