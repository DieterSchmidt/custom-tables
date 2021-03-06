<?php
/**
 * CustomTables Joomla! 3.x Native Component
 * @author Ivan komlev <support@joomlaboat.com>
 * @link http://www.joomlaboat.com
 * @license GNU/GPL
 **/

// no direct access
defined('_JEXEC') or die('Restricted access');

class tagProcessor_Field
{
    public static function process(&$Model,&$pagelayout,$add_label=false,$fieldNamePrefix='comes_')
    {

        tagProcessor_Field::ProcessFieldTitles($Model,$pagelayout,$add_label,$fieldNamePrefix);

    }

    protected static function ProcessFieldTitles(&$Model,&$pagelayout,$add_label=false,$fieldNamePrefix)
	{
		//field title
        if($add_label)
        {
            foreach($Model->esfields as $esfield)
            {
                if($esfield['type']=='dummy')
                {
                    $field_label=$esfield['fieldtitle'.$Model->langpostfix];
                }
                else
                {
                    $title=$esfield['fieldtitle'.$Model->langpostfix];
                    $description=str_replace('"','',$esfield['description'.$Model->langpostfix]);
                    $isrequired=(bool)$esfield['isrequired'];

                    $field_label='<label id="'.$fieldNamePrefix.$esfield['fieldname'].'-lbl" for="'.$fieldNamePrefix.$esfield['fieldname'].'" ';
                    $class=($description!='' ? 'hasPopover' : '').''.($isrequired ? ' required' : '');

                    if($class!='')
                        $field_label.=' class="'.$class.'"';

                    $field_label.=' title="'.$title.'"';

                    if($description)
                        $field_label.=' data-content="'.$description.'"';

                    $field_label.=' data-original-title="'.$title.'">'.$title;

                    if($isrequired)
                        $field_label.='<span class="star">&#160;*</span>';

                    $field_label.='</label>';
                }
            	$pagelayout=str_replace('*'.$esfield['fieldname'].'*',$field_label,$pagelayout);
            }
        }
        else
        {
            foreach($Model->esfields as $esfield)
            {
                if(!array_key_exists('fieldtitle'.$Model->langpostfix,$esfield))
				{
					JFactory::getApplication()->enqueueMessage(
						JoomlaBasicMisc::JTextExtended('COM_CUSTOMTABLES_ERROR_LANGFIELDNOTFOUND' ), 'Error');
                                        
                    $pagelayout=str_replace('*'.$esfield['fieldname'].'*','*fieldtitle'.$Model->langpostfix.' - not found*',$pagelayout);
				}
                else
                    $pagelayout=str_replace('*'.$esfield['fieldname'].'*',$esfield['fieldtitle'.$Model->langpostfix],$pagelayout);
            }
        }
		return $pagelayout;
	}


}
