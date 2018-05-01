<?php

namespace app\modules\files\models\upload;

use yii\imagine\Image;
use yii\base\{InvalidConfigException, InvalidValueException};
use yii\helpers\{ArrayHelper, Inflector};
use Aws\S3\{S3ClientInterface, S3MultiRegionClient};
use app\modules\files\models\S3FilesOptions;
use app\modules\files\helpers\S3Files;
use app\modules\files\Module;
use app\modules\files\components\ThumbConfig;
use app\modules\files\interfaces\{ThumbConfigInterface, UploadModelInterface};

/**
 * Class S3Upload
 *
 * @property string $s3Domain Amazon web services S3 domain.
 * @property string $s3Bucket Amazon web services S3 bucket.
 * @property S3MultiRegionClient|S3ClientInterface $s3Client Amazon web services SDK S3 client.
 * @property string $originalContent Binary contente of the original file.
 * @property array $objectsForDelete Objects for delete (files in the S3 directory).
 *
 * @package Itstructure\FilesModule\models
 *
 * @author Andrey Girnik <girnikandrey@gmail.com>
 */
class S3Upload extends BaseUpload implements UploadModelInterface
{
    const DIR_LENGTH_FIRST = 2;
    const DIR_LENGTH_SECOND = 4;

    const BUCKET_ROOT = 's3://';
    const BUCKET_DIR_SEPARATOR = '/';

    /**
     * Amazon web services S3 domain.
     * @var string
     */
    public $s3Domain;

    /**
     * Amazon web services S3 bucket.
     * @var string
     */
    public $s3Bucket;

    /**
     * Amazon web services SDK S3 client.
     * @var S3ClientInterface|S3MultiRegionClient
     */
    private $s3Client;

    /**
     * Binary contente of the original file.
     * @var string
     */
    private $originalContent;

    /**
     * Objects for delete (files in the S3 directory).
     * @var array
     */
    private $objectsForDelete;

    /**
     * Initialize.
     */
    public function init()
    {
        if (null === $this->s3Client){
            throw new InvalidConfigException('S3 client is not defined correctly.');
        }

        if (null === $this->s3Bucket || !is_string($this->s3Bucket)){
            throw new InvalidConfigException('S3 bucket is not defined correctly.');
        }

        if (null === $this->s3Domain || !is_string($this->s3Domain)){
            throw new InvalidConfigException('S3 domain is not defined correctly.');
        }

        $this->s3Domain = rtrim($this->s3Domain, '/');

        $this->s3Client->registerStreamWrapper();
    }

    /**
     * Set s3 client.
     * @param S3ClientInterface $s3Client
     */
    public function setS3Client(S3ClientInterface $s3Client): void
    {
        $this->s3Client = $s3Client;
    }

    /**
     * Get s3 client.
     * @return S3ClientInterface|null
     */
    public function getS3Client()
    {
        return $this->s3Client;
    }

    /**
     * Get storage type - aws.
     * @return string
     */
    protected function getStorageType(): string
    {
        return Module::STORAGE_TYPE_S3;
    }

    /**
     * Set some params for upload.
     * It is needed to set the next parameters:
     * $this->uploadDir
     * $this->uploadPath
     * $this->outFileName
     * @throws InvalidConfigException
     * @return void
     */
    protected function setParamsForSave(): void
    {
        $uploadDir = $this->getUploadDirConfig($this->file->type);
        $uploadDir = trim(str_replace('\\', self::BUCKET_DIR_SEPARATOR, $uploadDir), self::BUCKET_DIR_SEPARATOR);

        if (!empty($this->subDir)){
            $uploadDir = $uploadDir .
                self::BUCKET_DIR_SEPARATOR .
                trim(str_replace('\\', self::BUCKET_DIR_SEPARATOR, $this->subDir), self::BUCKET_DIR_SEPARATOR);
        }

        $this->uploadDir = $uploadDir .
            self::BUCKET_DIR_SEPARATOR . substr(md5(time()), 0, self::DIR_LENGTH_FIRST) .
            self::BUCKET_DIR_SEPARATOR . substr(md5(time()+1), 0, self::DIR_LENGTH_SECOND);

        $this->uploadPath = self::BUCKET_ROOT . $this->s3Bucket . self::BUCKET_DIR_SEPARATOR . $this->uploadDir;

        $this->outFileName = $this->renameFiles ?
            md5(time()+2).'.'.$this->file->extension :
            Inflector::slug($this->file->baseName).'.'. $this->file->extension;
    }

    /**
     * Set some params for delete.
     * It is needed to set the next parameters:
     * $this->objectsForDelete
     * @return void
     */
    protected function setParamsForDelete(): void
    {
        /** @var S3FilesOptions $s3fileOptions */
        $s3fileOptions = S3FilesOptions::find()->where([
            'mediafileId' => $this->mediafileModel->id
        ])->one();

        $objects = $this->s3Client->listObjects([
            'Bucket' => $s3fileOptions->bucket,
            'Prefix' => $s3fileOptions->prefix
        ]);

        $this->objectsForDelete = ArrayHelper::getColumn($objects['Contents'], 'Key');
    }

    /**
     * Send file to remote storage.
     * @return bool
     */
    protected function sendFile(): bool
    {
        $result = $this->s3Client->listObjects([
            'Bucket' => $this->s3Bucket,
            'Prefix' => 'application/e9/2044'
        ]);
        $keys = ArrayHelper::getColumn($result['Contents'], 'Key');
        echo '<pre>';
        var_dump($keys);die();

        $result = $this->s3Client->putObject([
            'ACL' => 'public-read',
            'SourceFile' => $this->file->tempName,
            'Key' => $this->uploadDir . self::BUCKET_DIR_SEPARATOR . $this->outFileName,
            //'Key' => 'application/e9/2045' . self::BUCKET_DIR_SEPARATOR . $this->outFileName,
            'Bucket' => $this->s3Bucket
        ]);
        /*$this->s3Client->deleteObject([
            'Key' => 'application/e9/2045/b479a7c9fe269aa5a3b6a6b023686a53.docx',
            'Bucket' => $this->s3Bucket
        ]);*/
        if ($result['ObjectURL']){
            $this->databaseUrl = $result['ObjectURL'];
            return true;
        }

        return false;
    }

    /**
     * Delete storage directory with original file and thumbs.
     * @return mixed
     */
    protected function deleteFiles()
    {
        S3Files::removeDirectory($this->directoryForDelete);

        return true;
    }

    /**
     * Create thumb.
     * @param ThumbConfigInterface|ThumbConfig $thumbConfig
     * @return string|null
     */
    protected function createThumb(ThumbConfigInterface $thumbConfig)
    {
        $originalFile = pathinfo($this->mediafileModel->url);

        $uploadThumbUrl = ltrim(str_replace($this->s3Domain, '', $originalFile['dirname']), self::BUCKET_DIR_SEPARATOR) .
                    self::BUCKET_DIR_SEPARATOR .
                    $this->getThumbFilename($originalFile['filename'],
                        $originalFile['extension'],
                        $thumbConfig->alias,
                        $thumbConfig->width,
                        $thumbConfig->height
                    );
        //var_dump($originalFile['dirname']);die();
        var_dump($this->mediafileModel->url);die();
        $thumbContent = Image::thumbnail(Image::getImagine()->load($this->getOriginalContent()),
            $thumbConfig->width,
            $thumbConfig->height,
            $thumbConfig->mode
        )->get($originalFile['extension'], [
            //'animated' => false
        ]);

        $result = $this->s3Client->putObject([
            'ACL' => 'public-read',
            'Body' => $thumbContent,
            'Key' => $uploadThumbUrl,
            'Bucket' => $this->s3Bucket
        ]);

        if ($result['ObjectURL']){
            return $result['ObjectURL'];
        }

        return null;
    }

    /**
     * Actions after main save.
     * @return mixed
     */
    protected function afterSave()
    {
        $this->addOwner();

        $this->setS3FileOptions($this->s3Bucket, $this->uploadDir);
    }

    /**
     * Get binary contente of the original file.
     * @throws InvalidValueException
     * @return string
     */
    private function getOriginalContent()
    {
        if (null === $this->originalContent){
            $this->originalContent = file_get_contents($this->mediafileModel->url);
        }

        if (!$this->originalContent){
            throw new InvalidValueException('Content from '.$this->mediafileModel->url.' can not be read.');
        }

        return $this->originalContent;
    }

    /**
     * Set S3 options for uploaded file in amazon S3 storage.
     * @param string $bucket
     * @param string $prefix
     * @return void
     */
    private function setS3FileOptions(string $bucket, string $prefix): void
    {
        if (null !== $this->file){
            $optionsModel = new S3FilesOptions();
            $optionsModel->mediafileId = $this->mediafileModel->id;
            $optionsModel->bucket = $bucket;
            $optionsModel->prefix = $prefix;
            $optionsModel->save();
        }
    }
}
