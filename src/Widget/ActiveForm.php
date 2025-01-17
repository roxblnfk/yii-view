<?php

namespace Yiisoft\Widget;

use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Html\Html;
use Yiisoft\View\InvalidCallException;
use Yiisoft\Widget\Event\AfterActiveFieldRender;
use Yiisoft\Widget\Event\BeforeActiveFieldRender;

/**
 * ActiveForm is a widget that builds an interactive HTML form for one or multiple data models.
 *
 * For more details and usage information on ActiveForm, see the [guide article on forms](guide:input-forms).
 */
class ActiveForm extends Widget
{
    /**
     * Add validation state class to container tag.
     */
    public const VALIDATION_STATE_ON_CONTAINER = 'container';
    /**
     * Add validation state class to input tag.
     */
    public const VALIDATION_STATE_ON_INPUT = 'input';

    /**
     * @var array|string the form action URL. This parameter will be processed by [[Url::to()]].
     *
     * @see method for specifying the HTTP method for this form.
     */
    public $action = '';
    /**
     * @var string the form submission method. This should be either `post` or `get`. Defaults to `post`.
     *
     * When you set this to `get` you may see the url parameters repeated on each request.
     * This is because the default value of [[action]] is set to be the current request url and each submit
     * will add new parameters instead of replacing existing ones.
     * You may set [[action]] explicitly to avoid this:
     *
     * ```php
     * $form = ActiveForm::begin([
     *     'method' => 'get',
     *     'action' => ['controller/action'],
     * ]);
     * ```
     */
    public $method = 'post';
    /**
     * @var array the HTML attributes (name-value pairs) for the form tag.
     *
     * @see Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $options = [];
    /**
     * @var string the default field class name when calling [[field()]] to create a new field.
     *
     * @see fieldConfig
     */
    public $fieldClass = ActiveField::class;
    /**
     * @var array|\Closure the default configuration used by [[field()]] when creating a new field object.
     *                     This can be either a configuration array or an anonymous function returning a configuration array.
     *                     If the latter, the signature should be as follows:
     *
     * ```php
     * function ($model, $attribute)
     * ```
     *
     * The value of this property will be merged recursively with the `$options` parameter passed to [[field()]].
     *
     * @see fieldClass
     */
    public $fieldConfig = [];
    /**
     * @var bool whether to perform encoding on the error summary.
     */
    public $encodeErrorSummary = true;
    /**
     * @var string the default CSS class for the error summary container.
     *
     * @see errorSummary()
     */
    public $errorSummaryCssClass = 'error-summary';
    /**
     * @var string the CSS class that is added to a field container when the associated attribute is required.
     */
    public $requiredCssClass = 'required';
    /**
     * @var string the CSS class that is added to a field container when the associated attribute has validation error.
     */
    public $errorCssClass = 'has-error';
    /**
     * @var string the CSS class that is added to a field container when the associated attribute is successfully validated.
     */
    public $successCssClass = 'has-success';
    /**
     * @var string the CSS class that is added to a field container when the associated attribute is being validated.
     */
    public $validatingCssClass = 'validating';
    /**
     * @var string where to render validation state class
     *             Could be either "container" or "input".
     *             Default is "container".
     */
    public $validationStateOn = self::VALIDATION_STATE_ON_CONTAINER;
    /**
     * @var bool whether to enable client-side data validation.
     *           If [[ActiveField::enableClientValidation]] is set, its value will take precedence for that input field.
     */
    public $enableClientValidation = true;
    /**
     * @var bool whether to enable AJAX-based data validation.
     *           If [[ActiveField::enableAjaxValidation]] is set, its value will take precedence for that input field.
     */
    public $enableAjaxValidation = false;
    /**
     * @var array|string the URL for performing AJAX-based validation. This property will be processed by
     *                   [[Url::to()]]. Please refer to [[Url::to()]] for more details on how to configure this property.
     *                   If this property is not set, it will take the value of the form's action attribute.
     */
    public $validationUrl;
    /**
     * @var bool whether to perform validation when the form is submitted.
     */
    public $validateOnSubmit = true;
    /**
     * @var bool whether to perform validation when the value of an input field is changed.
     *           If [[ActiveField::validateOnChange]] is set, its value will take precedence for that input field.
     */
    public $validateOnChange = true;
    /**
     * @var bool whether to perform validation when an input field loses focus.
     *           If [[ActiveField::$validateOnBlur]] is set, its value will take precedence for that input field.
     */
    public $validateOnBlur = true;
    /**
     * @var bool whether to perform validation while the user is typing in an input field.
     *           If [[ActiveField::validateOnType]] is set, its value will take precedence for that input field.
     *
     * @see validationDelay
     */
    public $validateOnType = false;
    /**
     * @var int number of milliseconds that the validation should be delayed when the user types in the field
     *          and [[validateOnType]] is set `true`.
     *          If [[ActiveField::validationDelay]] is set, its value will take precedence for that input field.
     */
    public $validationDelay = 500;
    /**
     * @var string the name of the GET parameter indicating the validation request is an AJAX request.
     */
    public $ajaxParam = 'ajax';
    /**
     * @var string the type of data that you're expecting back from the server.
     */
    public $ajaxDataType = 'json';
    /**
     * @var bool whether to scroll to the first error after validation.
     */
    public $scrollToError = true;
    /**
     * @var int offset in pixels that should be added when scrolling to the first error.
     */
    public $scrollToErrorOffset = 0;

    /**
     * @var ActiveField[] the ActiveField objects that are currently active
     */
    private $_fields = [];

    /**
     * Initializes the widget.
     * This renders the form open tag.
     */
    public function init(): void
    {
        parent::init();
        if (!isset($this->options['id'])) {
            $this->options['id'] = $this->getId();
        }
        ob_start();
        ob_implicit_flush(false);
    }

    /**
     * Runs the widget.
     * This registers the necessary JavaScript code and renders the form open and close tags.
     *
     * @throws InvalidCallException if `beginField()` and `endField()` calls are not matching.
     */
    public function run(): string
    {
        if (!empty($this->_fields)) {
            throw new InvalidCallException('Each beginField() should have a matching endField() call.');
        }

        $content = ob_get_clean();
        $html = Html::beginForm($this->action, $this->method, $this->options);
        $html .= $content;
        $html .= Html::endForm();

        return $html;
    }

    /**
     * Generates a summary of the validation errors.
     * If there is no validation error, an empty error summary markup will still be generated, but it will be hidden.
     *
     * @param Model|Model[] $models  the model(s) associated with this form.
     * @param array         $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - `header`: string, the header HTML for the error summary. If not set, a default prompt string will be used.
     * - `footer`: string, the footer HTML for the error summary.
     *
     * The rest of the options will be rendered as the attributes of the container tag. The values will
     * be HTML-encoded using [[Html::encode()]]. If a value is `null`, the corresponding attribute will not be rendered.
     *
     * @return string the generated error summary.
     *
     * @see errorSummaryCssClass
     */
    public function errorSummary($models, $options = [])
    {
        Html::addCssClass($options, $this->errorSummaryCssClass);
        $options['encode'] = $this->encodeErrorSummary;

        return Html::errorSummary($models, $options);
    }

    /**
     * Generates a form field.
     * A form field is associated with a model and an attribute. It contains a label, an input and an error message
     * and use them to interact with end users to collect their inputs for the attribute.
     *
     * @param Model  $model     the data model.
     * @param string $attribute the attribute name or expression. See [[Html::getAttributeName()]] for the format
     *                          about attribute expression.
     * @param array  $options   the additional configurations for the field object. These are properties of [[ActiveField]]
     *                          or a subclass, depending on the value of [[fieldClass]].
     *
     * @return ActiveField the created ActiveField object.
     *
     * @see fieldConfig
     */
    public function field($model, $attribute, $options = [])
    {
        $config = $this->fieldConfig;
        if ($config instanceof \Closure) {
            $config = call_user_func($config, $model, $attribute);
        }
        if (!isset($config['__class'])) {
            $config['__class'] = $this->fieldClass;
        }

        return $this->app->createObject(ArrayHelper::merge($config, $options, [
            'model'     => $model,
            'attribute' => $attribute,
            'form'      => $this,
        ]));
    }

    /**
     * Begins a form field.
     * This method will create a new form field and returns its opening tag.
     * You should call [[endField()]] afterwards.
     *
     * @param Model  $model     the data model.
     * @param string $attribute the attribute name or expression. See [[Html::getAttributeName()]] for the format
     *                          about attribute expression.
     * @param array  $options   the additional configurations for the field object.
     *
     * @return string the opening tag.
     *
     * @see endField()
     * @see field()
     */
    public function beginField($model, $attribute, $options = [])
    {
        $field = $this->field($model, $attribute, $options);
        $this->_fields[] = $field;

        return $field->begin();
    }

    /**
     * Ends a form field.
     * This method will return the closing tag of an active form field started by [[beginField()]].
     *
     * @throws InvalidCallException if this method is called without a prior [[beginField()]] call.
     *
     * @return string the closing tag of the form field.
     */
    public function endField()
    {
        $field = array_pop($this->_fields);
        if ($field instanceof ActiveField) {
            return $field->end();
        }

        throw new InvalidCallException('Mismatching endField() call.');
    }

    /**
     * Validates one or several models and returns an error message array indexed by the attribute IDs.
     * This is a helper method that simplifies the way of writing AJAX validation code.
     *
     * For example, you may use the following code in a controller action to respond
     * to an AJAX validation request:
     *
     * ```php
     * $model = new Post;
     * $model->load(Yii::getApp()->request->post());
     * if (Yii::getApp()->request->isAjax) {
     *     Yii::getApp()->response->format = Response::FORMAT_JSON;
     *     return ActiveForm::validate($model);
     * }
     * // ... respond to non-AJAX request ...
     * ```
     *
     * To validate multiple models, simply pass each model as a parameter to this method, like
     * the following:
     *
     * ```php
     * ActiveForm::validate($model1, $model2, ...);
     * ```
     *
     * @param Model $model      the model to be validated.
     * @param mixed $attributes list of attributes that should be validated.
     *                          If this parameter is empty, it means any attribute listed in the applicable
     *                          validation rules should be validated.
     *
     * When this method is used to validate multiple models, this parameter will be interpreted
     * as a model.
     *
     * @return array the error message array indexed by the attribute IDs.
     */
    public static function validate($model, $attributes = null)
    {
        $result = [];
        if ($attributes instanceof Model) {
            // validating multiple models
            $models = func_get_args();
            $attributes = null;
        } else {
            $models = [$model];
        }
        /* @var $model Model */
        foreach ($models as $model) {
            $model->validate($attributes);
            foreach ($model->getErrors() as $attribute => $errors) {
                $result[Html::getInputId($model, $attribute)] = $errors;
            }
        }

        return $result;
    }

    /**
     * Validates an array of model instances and returns an error message array indexed by the attribute IDs.
     * This is a helper method that simplifies the way of writing AJAX validation code for tabular input.
     *
     * For example, you may use the following code in a controller action to respond
     * to an AJAX validation request:
     *
     * ```php
     * // ... load $models ...
     * if (Yii::getApp()->request->isAjax) {
     *     Yii::getApp()->response->format = Response::FORMAT_JSON;
     *     return ActiveForm::validateMultiple($models);
     * }
     * // ... respond to non-AJAX request ...
     * ```
     *
     * @param array $models     an array of models to be validated.
     * @param mixed $attributes list of attributes that should be validated.
     *                          If this parameter is empty, it means any attribute listed in the applicable
     *                          validation rules should be validated.
     *
     * @return array the error message array indexed by the attribute IDs.
     */
    public static function validateMultiple($models, $attributes = null)
    {
        $result = [];
        /* @var $model Model */
        foreach ($models as $i => $model) {
            $model->validate($attributes);
            foreach ($model->getErrors() as $attribute => $errors) {
                $result[Html::getInputId($model, "[$i]".$attribute)] = $errors;
            }
        }

        return $result;
    }

    /**
     * This method is invoked right before an ActiveField is rendered.
     * The method will trigger the [[BeforeActiveFieldRender]] event.
     *
     * @param ActiveField $field active field to be rendered.
     */
    public function beforeFieldRender(ActiveField $field)
    {
        self::$eventDispatcher->dispatch(new BeforeActiveFieldRender($field));
    }

    /**
     * This method is invoked right after an ActiveField is rendered.
     * The method will trigger the [[AfterActiveFieldRender]] event.
     *
     * @param ActiveField $field active field to be rendered.
     */
    public function afterFieldRender(ActiveField $field)
    {
        self::$eventDispatcher->dispatch(new AfterActiveFieldRender($field));
    }
}
