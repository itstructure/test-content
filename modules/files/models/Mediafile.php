<?php

namespace app\modules\files\models;

use yii\helpers\ArrayHelper;
use app\modules\files\Module;
use app\modules\files\helpers\Html;
use app\modules\files\interfaces\UploadModelInterface;

/**
 * This is the model class for table "mediafiles".
 *
 * @property int $id
 * @property string $filename
 * @property string $type
 * @property string $url
 * @property string $alt
 * @property int $size
 * @property string $title
 * @property string $description
 * @property string $thumbs
 * @property string $storage
 * @property string $advance
 * @property int $created_at
 * @property int $updated_at
 * @property Module $_module
 * @property OwnersMediafiles[] $ownersMediafiles
 *
 * @package Itstructure\FilesModule\models
 */
class Mediafile extends ActiveRecord
{
    /**
     * @var Module
     */
    private $_module;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'mediafiles';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [
                [
                    'filename',
                    'type',
                    'url',
                    'size',
                    'storage',
                ],
                'required',
            ],
            [
                [
                    'alt',
                    'title',
                    'description',
                    'thumbs',
                    'storage',
                    'advance',
                ],
                'string',
            ],
            [
                [
                    'size',
                ],
                'integer',
            ],
            [
                [
                    'filename',
                    'type',
                    'url',
                ],
                'string',
                'max' => 255,
            ],
            [
                [
                    'created_at',
                    'updated_at',
                ],
                'safe',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'filename' => 'Filename',
            'type' => 'Type',
            'url' => 'Url',
            'alt' => 'Alt',
            'size' => 'Size',
            'title' => 'Title',
            'description' => 'Description',
            'thumbs' => 'Thumbs',
            'storage' => 'Storage',
            'advance' => 'Advance',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * @return Module
     */
    public function getModule(): Module
    {

        if ($this->_module === null){
            $this->_module = Module::getInstance();
        }

        return $this->_module;
    }

    /**
     * Find model by url.
     *
     * @param string $url
     *
     * @return Mediafile
     */
    public static function findByUrl(string $url): Mediafile
    {
        return self::findOne(['url' => $url]);
    }

    /**
     * Search models by file types.
     *
     * @param array $types
     *
     * @return ActiveRecord|array
     */
    public static function findByTypes(array $types): ActiveRecord
    {
        return self::find()->filterWhere(['in', 'type', $types])->all();
    }

    /**
     * Add owner to mediafiles table.
     *
     * @param int    $ownerId
     * @param string $owner
     * @param string $ownerAttribute
     *
     * @return bool
     */
    public function addOwner(int $ownerId, string $owner, string $ownerAttribute): bool
    {
        $owners = new OwnersMediafiles();
        $owners->mediafileId = $this->id;
        $owners->owner = $owner;
        $owners->ownerId = $ownerId;
        $owners->ownerAttribute = $ownerAttribute;

        return $owners->save();
    }

    /**
     * Remove this mediafile owner.
     *
     * @param int    $ownerId
     * @param string $owner
     * @param string $ownerAttribute
     *
     * @return bool
     */
    public static function removeOwner(int $ownerId, string $owner, string $ownerAttribute): bool
    {
        $deleted = OwnersMediafiles::deleteAll([
            'ownerId' => $ownerId,
            'owner' => $owner,
            'ownerAttribute' => $ownerAttribute,
        ]);

        return $deleted > 0;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOwners()
    {
        return $this->hasMany(OwnersMediafiles::class, ['mediafileId' => 'id']);
    }

    /**
     * Get preview html.
     *
     * @param string $baseUrl
     * @param string $location
     * @param array $options
     *
     * @return string
     */
    public function getPreview($baseUrl = '', string $location, array $options = []): string
    {
        $module = $this->getModule();

        if ($this->isImage()){
            $options = ArrayHelper::merge($module->getPreviewOptions(UploadModelInterface::FILE_TYPE_IMAGE, $location), $options);

            if (empty($options['alt'])) {
                $options['alt'] = $this->alt;
            }

            if (isset($options['alias']) && is_string($options['alias'])){
                return Html::img($this->getThumbUrl($options['alias']), $options);
            }

            return Html::img($this->getThumbUrl(Module::DEFAULT_THUMB_ALIAS), $options);

        } elseif ($this->isAudio()){
            $options = ArrayHelper::merge($module->getPreviewOptions(UploadModelInterface::FILE_TYPE_AUDIO, $location), $options);
            return Html::audio($this->url, ArrayHelper::merge([
                    'source' => [
                        'type' => $this->type
                    ]
                ], $options)
            );

        } elseif ($this->isVideo()){
            $options = ArrayHelper::merge($module->getPreviewOptions(UploadModelInterface::FILE_TYPE_VIDEO, $location), $options);
            return Html::video($this->url, ArrayHelper::merge([
                    'source' => [
                        'type' => $this->type
                    ]
                ], $options)
            );

        } elseif ($this->isApp()){
            $options = ArrayHelper::merge($module->getPreviewOptions(UploadModelInterface::FILE_TYPE_APP, $location), $options);
            return Html::img($this->getAppPreviewUrl($baseUrl), $options);

        } elseif ($this->isText()){
            $options = ArrayHelper::merge($module->getPreviewOptions(UploadModelInterface::FILE_TYPE_TEXT, $location), $options);
            return Html::img($this->getTextPreviewUrl($baseUrl), $options);

        } else {
            $options = ArrayHelper::merge($module->getPreviewOptions(UploadModelInterface::FILE_TYPE_OTHER, $location), $options);
            return Html::img($this->getOtherPreviewUrl($baseUrl), $options);
        }
    }

    /**
     * Get application preview url.
     *
     * @param string $baseUrl
     *
     * @return string
     */
    public function getAppPreviewUrl($baseUrl = ''): string
    {
        if (!empty($baseUrl) && is_string($baseUrl)){
            $root = $baseUrl.DIRECTORY_SEPARATOR;
        } else {
            $root = DIRECTORY_SEPARATOR;
        }
        $module = $this->getModule();

        if ($this->isExcel()){
            $url = $root . $module->thumbStubUrls[UploadModelInterface::FILE_TYPE_APP_EXCEL];

        } elseif ($this->isPdf()){
            $url = $root . $module->thumbStubUrls[UploadModelInterface::FILE_TYPE_APP_PDF];

        } elseif ($this->isWord()){
            $url = $root . $module->thumbStubUrls[UploadModelInterface::FILE_TYPE_APP_WORD];

        } else {
            $url = $root . $module->thumbStubUrls[UploadModelInterface::FILE_TYPE_APP];
        }

        return $url;
    }

    /**
     * Get text preview url.
     *
     * @param string $baseUrl
     *
     * @return string
     */
    public function getTextPreviewUrl($baseUrl = ''): string
    {
        if (!empty($baseUrl) && is_string($baseUrl)){
            $root = $baseUrl.DIRECTORY_SEPARATOR;
        } else {
            $root = DIRECTORY_SEPARATOR;
        }

        return $root . $this->getModule()->thumbStubUrls[UploadModelInterface::FILE_TYPE_APP];
    }

    /**
     * Get other preview url.
     *
     * @param string $baseUrl
     *
     * @return string
     */
    public function getOtherPreviewUrl($baseUrl = ''): string
    {
        if (!empty($baseUrl) && is_string($baseUrl)){
            $root = $baseUrl.DIRECTORY_SEPARATOR;
        } else {
            $root = DIRECTORY_SEPARATOR;
        }
        $module = $this->getModule();

        return $root . $module->thumbStubUrls[UploadModelInterface::FILE_TYPE_OTHER];
    }

    /**
     * Get thumbnails.
     *
     * @return array
     */
    public function getThumbs(): array
    {
        return unserialize($this->thumbs) ?: [];
    }

    /**
     * Get thumb url.
     *
     * @param string $alias
     *
     * @return string
     */
    public function getThumbUrl(string $alias): string
    {
        if ($alias === Module::ORIGINAL_THUMB_ALIAS) {
            return $this->url;
        }

        $thumbs = $this->getThumbs();

        return !empty($thumbs[$alias]) ? $thumbs[$alias] : '';
    }

    /**
     * Get thumb image.
     *
     * @param string $alias
     * @param array  $options
     *
     * @return string
     */
    public function getThumbImage(string $alias, array $options = []): string
    {
        $url = $this->getThumbUrl($alias);

        if (empty($url)) {
            return '';
        }

        if (empty($options['alt'])) {
            $options['alt'] = $this->alt;
        }

        return Html::img($url, $options);
    }

    /**
     * Get default thumbnail url.
     *
     * @param string $baseUrl
     *
     * @return string
     */
    public function getDefaultThumbUrl($baseUrl = ''): string
    {
        if ($this->isImage()){
            return $this->getThumbUrl(Module::DEFAULT_THUMB_ALIAS);

        } elseif ($this->isApp()){
            return $this->getAppPreviewUrl($baseUrl);

        } elseif ($this->isText()){
            return $this->getTextPreviewUrl($baseUrl);

        } else {
            return $this->getOtherPreviewUrl($baseUrl);
        }
    }

    /**
     * @return string file size
     */
    public function getFileSize()
    {
        \Yii::$app->formatter->sizeFormatBase = 1000;
        return \Yii::$app->formatter->asShortSize($this->size, 0);
    }

    /**
     * Check if the file is image.
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return strpos($this->type, UploadModelInterface::FILE_TYPE_IMAGE) !== false;
    }

    /**
     * Check if the file is audio.
     *
     * @return bool
     */
    public function isAudio(): bool
    {
        return strpos($this->type, UploadModelInterface::FILE_TYPE_AUDIO) !== false;
    }

    /**
     * Check if the file is video.
     *
     * @return bool
     */
    public function isVideo(): bool
    {
        return strpos($this->type, UploadModelInterface::FILE_TYPE_VIDEO) !== false;
    }

    /**
     * Check if the file is text.
     *
     * @return bool
     */
    public function isText(): bool
    {
        return strpos($this->type, UploadModelInterface::FILE_TYPE_TEXT) !== false;
    }

    /**
     * Check if the file is application.
     *
     * @return bool
     */
    public function isApp(): bool
    {
        return strpos($this->type, UploadModelInterface::FILE_TYPE_APP) !== false;
    }

    /**
     * Check if the file is excel.
     *
     * @return bool
     */
    public function isExcel(): bool
    {
        return strpos($this->type, UploadModelInterface::FILE_TYPE_APP_EXCEL) !== false;
    }

    /**
     * Check if the file is pdf.
     *
     * @return bool
     */
    public function isPdf(): bool
    {
        return strpos($this->type, UploadModelInterface::FILE_TYPE_APP_PDF) !== false;
    }

    /**
     * Check if the file is word.
     *
     * @return bool
     */
    public function isWord(): bool
    {
        return strpos($this->type, UploadModelInterface::FILE_TYPE_APP_WORD) !== false;
    }
}
