<?php
/**
 * AttachmentBehavior class file.
 *
 * @author Greg Molnar 
 * @link https://github.com/gregmolnar/yii-attachment-behavior/
 * @copyright Copyright &copy; Greg Molnar
 * @license http://opensource.org/licenses/bsd-license.php
 */

 /**
 * This behaviour will let you add attachments to your model  easily
 * you will need to add the following database fields to your model:
 * filename string
 * In your model behaviours:
 * 
 *    'image' => array(
 *               'class' => 'ext.AttachmentBehavior.AttachmentBehavior',
 *               'attribute' => 'filename',
 *               //'fallback_image' => 'images/sample_image.gif',
 *               'path' => "uploads/:model/:id.:ext",
 *               'processors' => array(
 *                   array(
 *                       'class' => 'ImagickProcessor',
 *                       'method' => 'resize',
 *                       'params' => array(
 *                           'width' => 310,
 *                           'height' => 150,
 *                           'keepratio' => true
 *                       )
 *                   )
 *               ),
 *               'styles' => array(
 *                   'thumb' => '!100x60',
 *               )           
 *           ),
 *
 * @property string $path
 * @private string $filename
 * @private integer $filesize 
 * @private string $parsedPath
 * */
class AttachmentBehavior extends CActiveRecordBehavior {    
    
    /**
     * @property string folder to save the attachment
     */ 
    public $folder = 'uploads';
    
    /**
     * @property string path to save the attachment
     */ 
    public $path = ':folder/:id.:ext';
    
    /**
    * @property name of attribute which holds the attachment
    */  
    public $attribute = 'filename';
    
    /**
    * @property array of processors
    */
    public $processors = array();
    
    /**
     * @property array of styles needs to create
     * example:
     * array(
     *   'small' => '150x75',
     *  'medium' => '!250x70'
     * )
     */
    public $styles = array();
    
    /**
     * @property string $fallback_image placeholder image src.
     */
    public $fallback_image;
    
    private $file_extension, $filename;
    
    /**
     * getter method for the attachment.
     * if you call it like a property ($model->Attachment) it will return the base size.
     * if you have the styles specified you can get them like this:
     * $model->getAttachment('small')
     * @param string $style style to return
     */ 
    public function getAttachment($style = '')
    {
        if($style == ''){
            if($this->hasAttachment())
                return $this->Owner->{$this->attribute};
            elseif($this->fallback_image != '')
                return $this->fallback_image;
            else
                return '';
        }else{
            if(isset($this->styles[$style])){
                $im = preg_replace('/\.(.*)$/','-'.$style.'\\0',$this->Owner->{$this->attribute});                
                if(file_exists($im))
                    return  $im;
                elseif(isset($this->fallback_image))
                    return $this->fallback_image;                
            }
        }
        
    }
    
    /**
     * check if we have an attachment
     */
    public function hasAttachment()
    {
        return file_exists($this->Owner->{$this->attribute});
    }
    
    /**
     * deletes the attachment
     */
    public function deleteAttachment()
    {
        if(file_exists($this->Owner->{$this->attribute}))unlink($this->Owner->{$this->attribute});
        preg_match('/\.(.*)$/',$this->Owner->{$this->attribute},$matches);
        $this->file_extension = end($matches);
        if(!empty($this->styles)){
            $this->path = str_replace('.:ext','-:custom.:ext',$this->path);    
            foreach($this->styles as $style => $size){
                $path = $this->getParsedPath($style);
                if(file_exists($path))unlink($path);
            }
        }
    }
    
    public function afterDelete($event)
    {
        $this->deleteAttachment();
    }
    
    
    public function afterSave($event)
    {
        $file = AttachmentUploadedFile::getInstance($this->Owner,$this->attribute);
        if(!is_null($file)){
            if(!$this->Owner->isNewRecord){
                //delete previous attachment
                if(file_exists($this->Owner->{$this->attribute}))unlink($this->Owner->{$this->attribute});
            }else{
                $this->Owner->isNewRecord = false;
            }
            preg_match('/\.(.*)$/',$file->name,$matches);
            $this->file_extension = end($matches);
            $this->filename = $file->name;
            $path = $this->parsedPath;
        
            preg_match('|^(.*[\\\/])|', $path, $match);
            $folder = end($match);      
            if(!is_dir($folder))mkdir($folder, 0777, true);
        
            $file->saveAs($path,false);
            $file_type = filetype($path);       
            $this->Owner->saveAttributes(array($this->attribute => $path));
            $attributes = $this->Owner->attributes;
            
            if(array_key_exists('file_size', $attributes)){
                $this->Owner->saveAttributes(array('file_size' => filesize($path)));
            }
            if(array_key_exists('file_type', $attributes)){
                $this->Owner->saveAttributes(array('file_type' => mime_content_type($path)));
            }
            if(array_key_exists('extension', $attributes)){
                $this->Owner->saveAttributes(array('extension' => $this->file_extension));
            }        
        
            #processors
            if(!empty($this->processors)){
                foreach($this->processors as $processor){
                    $p = new $processor['class']($path);
                    $p->output_path = $path;
                    $p->{$processor['method']}($processor['params']);
                }
            }        
            /**
             * process resize if we have multiple sizes
             */
            if(!empty($this->styles)){
                $this->path = str_replace('.:ext','-:custom.:ext',$this->path);                
                if(class_exists('Imagick',false)){
                    $processor = new ImagickProcessor($path);
                }else{
                    if(!function_exists("gd_info"))
                        throw new CException ('GD or Imagick extension needs to image resize.');
                    $processor = new GDProcessor($path);                    
                }
                // if the dimensions start with an ! the keepratio will be false
                foreach($this->styles as $style => $size){
                    $processor->output_path = $this->getParsedPath($style);
                    $s = explode('x',$size);
                    if($s[0][0] == '!'){
                        $s[0] = ltrim($s[0], '!');
                        $keepratio = false;
                    }else{
                        $keepratio = true;
                    }
                    $processor->resize(array('width' => $s[0], 'height' => $s[1], 'keepratio' => $keepratio));
                }
            }
        }
        return true;
    }

    public function getParsedPath($custom = '')
    {
        $needle = array(':folder', ':model', ':id', ':ext', ':filename', ':custom');
        $replacement = array($this->folder, get_class($this->Owner), $this->Owner->primaryKey,$this->file_extension, $this->filename, $custom);
        if(preg_match_all('/:\\{([^\\}]+)\\}/', $this->path, $matches, PREG_SET_ORDER)) {
            foreach($matches as $match) {
                $valuePath = explode('.', $match[1]);
                $value = $this->owner;
                foreach($valuePath as $attributeName) {
                    if(is_object($value))
                        $value = $value->{$attributeName};
                }
                $needle[] = $match[0];
                $replacement[] = $value;
            }
        }
        return str_replace($needle, $replacement, $this->path);
    }


    public function UnsafeAttribute($name, $value)
    {
        var_dump(true);exit;
        if($name != $this->attribute)
            parent::onUnsafeAttribute($name, $value);
    }
}

class AttachmentUploadedFile
{
    public $name, $_error ;

    public static function getInstance($model, $attribute){
        $c =  new AttachmentUploadedFile;
        $c->modelName = get_class($model);
        if(empty($_FILES[$c->modelName]) || empty($_FILES[$c->modelName]['name'][$attribute]))return null;
        $c->name = $_FILES[$c->modelName]['name'][$attribute];
        $c->file_name = $_FILES[$c->modelName]['tmp_name'][$attribute];
        if(!file_exists($c->file_name))return null;
        return $c;
    }
    
    public function saveAs($file){
        if($this->_error==UPLOAD_ERR_OK){
            if(is_uploaded_file($this->file_name)){
                return move_uploaded_file($this->file_name, $file);
            }else{
                return rename($this->file_name, $file);    
            }            
        }else return false;
    }
}

/**
 * GD Imageprocessor
 */ 
class GDProcessor
{
    
    public $image,$file_extension,$output_path;
    
    public function __construct($image)
    {
        $this->image = $image;
        preg_match('/\.(.*)$/',$this->image,$matches);
        $this->file_extension = end($matches);    
    }
    
    public function resize($params)
    {
        switch(strtolower($this->file_extension)){
            case "jpg";
                $original = imagecreatefromjpeg($this->image);
                $output_function = 'imagejpeg';
            break;
            case "jpeg";
                $original = imagecreatefromjpeg($this->image);
                $output_function = 'imagejpeg';
            break;
            case "gif";
                $original = imagecreatefromgif($this->image);
                $output_function = 'imagegif';
            break;
            case "png";
                $original = imagecreatefrompng($this->image);
                $output_function = 'imagepng';
            break;
            default;
                throw new CException ("'{$this->file_extension}' is not supported.");
            break;
        }   
     
        $old_width = imagesx($original);
        $old_height = imagesy($original);
        
        $area = $params['width'] * $params['height']; 
        $old_area = $old_width * $old_height;
        $ratio = sqrt($area / $old_area);
        $side_ratio = $old_width / $old_height;
        $new_height = ceil($old_height * $ratio);
        $new_width = ceil($old_width * $ratio);
        if($new_width < $new_height){
            //portrait
            $side_ratio = $new_width/$new_height;
            $new_height = $params['height'];
            $new_width = $params['height'] * $side_ratio;
        }
        if($old_width < $params['width'] and $old_height < $params['height']){
            //landscape
            $new_height = $old_height;
            $new_width = $old_width;
        }        
        $new = imagecreatetruecolor($new_width,$new_height);
        imagecopyresampled($new,$original,0,0,0,0,$new_width,$new_height,$old_width,$old_height);        
        $output_function($new,$this->output_path);
        imagedestroy($new);
        imagedestroy($original);
    }
}
class ImagickProcessor
{
    public $image,$file_extension,$output_path;
    
    public function __construct($image)
    {
        $this->image = $image;
        preg_match('/\.(.*)$/',$this->image,$matches);
        $this->file_extension = end($matches);
    }
    
    /**
     * @param array $params array('width' => integer, 'height' => integer, 'keepratio' => bool)
     */ 
    public function resize($params = array())
    {
        $im = new Imagick($this->image);
        $im->thumbnailImage($params['width'], $params['height'], $params['keepratio']);
        $im->writeImage($this->output_path);
    }
    
    public function setImageColorSpace($color_space = Imagick::COLORSPACE_GRAY)
    {
        $im = new Imagick($this->image);
        $im->setImageColorSpace($color_space);
        $im->writeImage($this->output_path);
    
    }
}
