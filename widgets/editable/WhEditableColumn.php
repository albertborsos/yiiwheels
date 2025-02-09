<?php
/**
 * @copyright Copyright (c) 2013 2amigOS! Consulting Group LLC
 * @link http://2amigos.us
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */

Yii::import('yiiwheels.widgets.editable.WhEditableField');
Yii::import('zii.widgets.grid.CDataColumn');
Yii::import('yiiwheels.widgets.editable.WhEditable');

/**
 * WhEditableColumn class
 *
 * Makes editable one column in CGridView.
 *
 * @author Antonio Ramirez <amigo.cobos@gmail.com>
 * @package YiiWheels.widgets.editable
 *
 * @author Vitaliy Potapov <noginsk@rambler.ru>
 * @link https://github.com/vitalets/x-editable-yii
 * @copyright Copyright &copy; Vitaliy Potapov 2012
 * @version 1.3.1
 */
class WhEditableColumn extends CDataColumn
{
    /**
     * @var array editable config options.
     * @see EditableField config
     */
    public $editable = [];

    public function init()
    {
        if (!$this->name) {
            throw new CException('You should provide name for EditableColumn');
        }

        parent::init();

        //need to attach ajaxUpdate handler to refresh editables on pagination and sort
        WhEditable::attachAjaxUpdateEvent($this->grid);
    }

    /**
     * Renders data cell content
     *
     * @param int $row
     * @param mixed $data
     */
    protected function renderDataCellContent($row, $data)
    {
        $isModel = $data instanceOf CModel;

        if ($isModel) {
            $widgetClass = 'WhEditableField';
            $options = [
                'model' => $data,
                'attribute' => empty($this->editable['attribute']) ? $this->name : $this->editable['attribute'],
            ];

            //if value defined in column config --> we should evaluate it
            //and pass to widget via `text` option: set flag `passText` = true
            $passText = !empty($this->value);
        } else {
            $widgetClass = 'WhEditable';
            $options = [
                'pk' => $data[$this->grid->dataProvider->keyField],
                'name' => empty($this->editable['name']) ? $this->name : $this->editable['name'],
            ];

            $passText = true;
            //if autotext will be applied, do not pass `text` option (pass `value` instead)
            if (empty($this->value) && WhEditable::isAutotext(
                    $this->editable,
                    $this->editable['type'] ?? ''
                )
            ) {
                $options['value'] = $data[$this->name];
                $passText = false;
            }
        }

        //for live update
        $options['liveTarget'] = $this->grid->id;

        $options = CMap::mergeArray($this->editable, $options);

        //if value defined for column --> use it as element text
        if ($passText) {
            ob_start();
            parent::renderDataCellContent($row, $data);
            $text = ob_get_clean();
            $options['text'] = $text;
            $options['encode'] = false;
        }

        //apply may be a string expression, see https://github.com/vitalets/x-editable-yii/issues/33
        if (isset($options['apply']) && is_string($options['apply'])) {
            $options['apply'] = $this->evaluateExpression($options['apply'], ['data' => $data, 'row' => $row]);
        }

        //evaluate htmlOptions inside editable config as they can depend on $data
        //see https://github.com/vitalets/x-editable-yii/issues/40
        if (isset($options['htmlOptions']) && is_array($options['htmlOptions'])) {
            foreach ($options['htmlOptions'] as $k => $v) {
                if (is_string($v) && (str_contains($v, '$data') || str_contains($v, '$row'))) {
                    $options['htmlOptions'][$k] = $this->evaluateExpression($v, ['data' => $data, 'row' => $row]);
                }
            }
        }

        $this->grid->controller->widget($widgetClass, $options);
    }

    /**
     * Require this overwrite to show bootstrap sort icons
     */
    protected function renderHeaderCellContent()
    {

        if ($this->grid->enableSorting && $this->sortable && $this->name !== null) {
            $sort = $this->grid->dataProvider->getSort();
            $label = $this->header ?? $sort->resolveLabel($this->name);

            if ($sort->resolveAttribute($this->name) !== false)
                $label .= '<span class="caret"></span>';

            echo $sort->link($this->name, $label, ['class' => 'sort-link']);
        } else {
            if ($this->name !== null && $this->header === null) {
                if ($this->grid->dataProvider instanceof CActiveDataProvider)
                    echo CHtml::encode($this->grid->dataProvider->model->getAttributeLabel($this->name));
                else
                    echo CHtml::encode($this->name);
            } else
                parent::renderHeaderCellContent();
        }
    }

    /**
     * Require this overwrite to show bootstrap filter field
     */
    public function renderFilterCell()
    {
        echo '<td><div class="filter-container">';
        $this->renderFilterCellContent();
        echo '</div></td>';
    }
}
