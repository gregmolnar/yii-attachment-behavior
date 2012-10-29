Yii Attachment Behavior
=======================

File uploads are a very common task and the goal of this extension is to make it easy and DRY.
This extension uploads the file to the server, renames the file and saves the path to the database.

Installation
------------

Copy the AttachmentBehavior folder into the extensions folder of you app.

Usage
----

In your model attach the behavior:
```php
public function behaviors()
{
	return array(
		'image' => array(
			'class' => 'ext.AttachmentBehavior.AttachmentBehavior',
			# Should be a DB field to store path/filename
			'attribute' => 'filename',
			# Default image to return if no image path is found in the DB
			//'fallback_image' => 'images/sample_image.gif',
			'path' => "uploads/:model/:id.:ext",
			'processors' => array(
				array(
					# Currently GD Image Processor and Imagick Supported
					'class' => 'ImagickProcessor',
					'method' => 'resize',
					'params' => array(
						'width' => 310,
						'height' => 150,
						'keepratio' => true
					)
				)
			),
			'styles' => array(
				# name => size 
				# use ! if you would like 'keepratio' => false
				'thumb' => '!100x60',
			)			
		),
	);
}
```
At your form set the enctype to multipart/form data and add a file field:
```php
<?php $form=$this->beginWidget('CActiveForm', array(
  'id'=>'page-form',
	'enableAjaxValidation'=>false,
	'htmlOptions' => array(
		'enctype' => 'multipart/form-data'
	)
)); ?>

	<p class="note">Fields with <span class="required">*</span> are required.</p>

	<?php echo $form->errorSummary($model); ?>

	<div class="row">
		<?php echo $form->labelEx($model,'filename'); ?>
		<?php echo $form->fileField($model,'filename'); ?>
		<?php echo $form->error($model,'filename'); ?>
	</div>

	<div class="row buttons">
		<?php echo CHtml::submitButton($model->isNewRecord ? 'Create' : 'Save'); ?>
	</div>

<?php $this->endWidget(); ?>
```

Create the upload folder and upload a file.
To retrieve the image:
```php
echo '<img src="'.$model->getAttachment('thumb').'" />'; //thumbnail
echo '<img src="'.$model->Attachment.'" />'; //base image
```

Gotchas
-------
The filename attribute of the model needs to be unsafe otherwise on update if there is no new image upload it will wipe the filename from the DB.
```php 
public function rules()
	{
		return array(
			array('filename', 'unsafe'),
		);
	}
```
For multiple uploads create a model for your upload and use a has_many relation.

Additional Information
----------------------
This behavior can be used for any file attachments. Remove the processor for non images.

Contribution
------------
Pull request are welcomed.

Plans
-----
* validation
* separate fallback image for styles
* fix "Trying to set unsafe attribute" warning
* document different image processors 

