<?php
/**
 * CustomTables Joomla! 3.x Native Component
 * @package Custom Tables
 * @author Ivan komlev <support@joomlaboat.com>
 * @link http://www.joomlaboat.com
 * @copyright Copyright (C) 2018-2020. All Rights Reserved
 * @license GNU/GPL Version 2 or later - http://www.gnu.org/licenses/gpl-2.0.html
 **/

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.model');

$adminlib=JPATH_SITE.DIRECTORY_SEPARATOR.'administrator'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_customtables'.DIRECTORY_SEPARATOR.'libraries'.DIRECTORY_SEPARATOR;
require_once($adminlib.'customtablesmisc.php');
require_once($adminlib.'misc.php');
require_once($adminlib.'languages.php');
require_once($adminlib.'tables.php');
require_once($adminlib.'fields.php');
$sitelib=JPATH_SITE.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_customtables'.DIRECTORY_SEPARATOR.'libraries'.DIRECTORY_SEPARATOR;
require_once($sitelib.'layout.php');
require_once($sitelib.'filtering.php');
require_once($sitelib.'ordering.php');
require_once($sitelib.'logs.php');

class CustomTablesModelCatalog extends JModelLegacy
{
		var $es;
		var $filtering;
		var $esTable;
		var $TotalRows=0;
		var $_pagination = null;

		var $order_list;
		var $order_values;
		var $esordering;

		var $establename;
		var $tablerow;
		var $estableid;

		var $esfields;
		var $tablecustomphp;
		var $LanguageList;

		var $filterparam;

		var $LangMisc;
		var $langpostfix;

		var $class="mag-phone mag-field formCol";
		var $columns;

		var $ShowDatailsLink;

		var $imagefolder;
		var $imagefolderweb;
		var $imagegalleryprefix;
		var $showdescription;

		var $showpagination;
		var $groupby;

		var $useridfieldname;

		var $showpublished;

		var $params;
		var $blockExternalVars;

		var $Itemid;

		var $layout;
		
		var $shownavigation;

		var $limit;
		var $limitstart;
		var $recordlist;


		var $showcartitemsonly;
		var $showcartitemsprefix;


		var $imagegalleries;
		var $fileboxes;

		var $PathValue;

		var $current_url;
		var $current_sef_url;
		var $current_sef_url_query;
		var $alias_fieldname;

		var $encoded_current_url;
		var $userid;
		var $isUserAdministrator;
		var $print;
		var $clean;
		var $frmt;
		var $WebsiteRoot;

		function __construct()
		{
			parent::__construct();
			$jinput=JFactory::getApplication()->input;

			$this->current_url=JoomlaBasicMisc::curPageURL();
			$this->current_sef_url='';
			$this->current_sef_url_query='';
			$this->alias_fieldname='';

			$this->encoded_current_url=base64_encode($this->current_url);

			$mainframe = JFactory::getApplication();
			if($mainframe->getCfg( 'sef' ))
			{
				$this->WebsiteRoot=JURI::root(true);
				if($this->WebsiteRoot=='' or $this->WebsiteRoot[strlen($this->WebsiteRoot)-1]!='/') //Root must have slash / in the end
					$this->WebsiteRoot.='/';
			}
			else
				$this->WebsiteRoot='';


			$user = JFactory::getUser();
			$this->userid=$user->id;

			$this->isUserAdministrator=JoomlaBasicMisc::isUserAdmin($this->userid);
			$this->print=(bool)$jinput->getInt('print',0);
			$this->clean=(bool)$jinput->getInt('clean',0);


			$this->frmt=$jinput->getCmd('frmt','html');
		}

		function prepareSEFLinkBase()
		{
			if(strpos($this->current_url,'option=com_customtables')===false)
		        {
				$pair=explode('?',$this->current_url);
				$this->current_sef_url=$pair[0].'/';
				if(isset($pair[1]))
					$this->current_sef_url='?'.$pair[1];



				foreach($this->esfields as $fld)
				{
					if($fld['type']=='alias')
					{
						$this->alias_fieldname=$fld['fieldname'];
						break;
					}
				}
			}
		}

		function load(&$params,$blockExternalVars=false,$layout='')
		{
				$this->blockExternalVars=$blockExternalVars;
				$jinput = JFactory::getApplication()->input;

				$mainframe = JFactory::getApplication('site');
				$db = JFactory::getDBO();


				//libraries
				$this->es= new CustomTablesMisc;



				//get params
				if($this->blockExternalVars or (isset($params) and count($params)>1))
				{

					$this->params=$params;
				}
				else
				{
						$app		= JFactory::getApplication();
						$this->params=$app->getParams();


				}//if($this->blockExternalVars)


				if(!$this->blockExternalVars)
						$this->showpagination=$this->params->get('showpagination');
				else
						$this->showpagination=0;

				//misc

				$this->layout=$layout;
				$this->showpublished=(int)$this->params->get('showpublished');

				$this->shownavigation=$this->params->get( 'shownavigation' );
				$this->ShowDatailsLink=(bool)$this->params->get('linktodetails');

				$forceitemid=$this->params->get('forceitemid');
				if(isset($forceitemid) and $forceitemid!='')
				{
					//Find Itemid by alias
					if(((int)$forceitemid)>0)
						$this->Itemid=$forceitemid;
					else
					{
						if($forceitemid!="0")
							$this->Itemid=(int)JoomlaBasicMisc::FindItemidbyAlias($forceitemid);//Accepts menu Itemid and alias
						else
							$this->Itemid=$jinput->get('Itemid',0,'INT');
					}
				}
				else
				{
					$this->Itemid=$jinput->get('Itemid',0,'INT');
					$forceitemid=null;
				}

				$this->columns=0;//2(int)$this->params->get('columns');

				//Language

				$this->LangMisc	= new ESLanguages;
				$this->LanguageList=$this->LangMisc->getLanguageList();
				$this->langpostfix=$this->LangMisc->getLangPostfix();

				//ExtreSearch Table staff
				$this->esTable=new ESTables;

				$this->establename=$this->params->get( 'establename' );

				if($this->establename=='')
				{
					echo 'Table not selected';
					die ;
					return false;
				}

				$this->tablerow = $this->esTable->getTableRowByNameAssoc($this->establename);
				$this->estableid=$this->tablerow['id'];

				$this->tablecustomphp=$this->tablerow['customphp'];

				//	Fields
				$this->esfields = ESFields::getFields($this->estableid);



				//sorting

				$this->esordering=CTOrdering::loadOrderFields($this->blockExternalVars,$this->params,$this->esfields,$this->langpostfix,
									      $this->order_list,$this->order_values);

				$this->imagegalleries=array();
				$this->fileboxes=array();
				$this->useridfieldname='';

				//Get useridfield
				if($this->params->get('useridfield'))
				{
					$this->useridfieldname=$this->params->get('useridfield');
				}
				else
				{
						foreach($this->esfields as $fld)
						{
							if($fld['type']=='imagegallery')
								$this->imagegalleries[]=array($fld['fieldname'],$fld['fieldtitle'.$this->langpostfix]);

							if($fld['type']=='filebox')
								$this->fileboxes[]=array($fld['fieldname'],$fld['fieldtitle'.$this->langpostfix]);

							if($fld['type']=='userid')
								$this->useridfieldname=$fld['fieldname'];

						}
				}

				//Limit
				$this->applyLimits();
				
				//Grouping
				$this->groupby=$this->params->get('groupby');


				//Layout

				$this->LayoutProc=new LayoutProcessor;
				$this->LayoutProc->Model=$this;
				$this->LayoutProc->langpostfix=$this->langpostfix;
				$this->LayoutProc->fields=$this->esfields;


				$this->LayoutProc->es=$this->es;

				$this->LayoutProc->ShowDatailsLink=$this->ShowDatailsLink;
				$this->LayoutProc->establename=$this->establename;
				$this->LayoutProc->estableid=$this->estableid;
				$this->LayoutProc->imagefolder=$this->imagefolder;
				$this->LayoutProc->imagefolderweb=$this->imagefolderweb;
				$this->LayoutProc->imagegalleryprefix=$this->imagegalleryprefix;
				$this->LayoutProc->Itemid=$this->Itemid;


				//filtering set in back-end

				$this->filterparam='';
				if($this->blockExternalVars)
				{

						$this->filterparam=$this->params->get( 'filter' );
				}
				else
				{
						if($jinput->get('filter','','STRING'))
							$this->filterparam= str_replace(';','',$jinput->get('filter','','STRING'));
						else
							$this->filterparam=$this->params->get( 'filter' );
				}

				if($this->filterparam!='')
				{
					//Parse using layout, has no effect to layout itself
					$this->LayoutProc->layout=$this->filterparam;
					$this->filterparam=$this->LayoutProc->fillLayout(array(),null,'');
				}

				//user filtering from module
				$this->filter='';
				if(!$this->blockExternalVars)
				{
						if($jinput->get('where','','BASE64'))
						{
							$this->filter=$jinput->get('where','','BASE64');;
							$decodedurl=urldecode($this->filter);
							$decodedurl=str_replace(' ','+',$decodedurl);

							$bas=base64_decode($decodedurl);

							$paramwhere=str_replace('*','=',$bas);
							$paramwhere=str_replace('\\','',$bas);

							$paramwhere=str_replace(';','',$paramwhere);
							$paramwhere=str_replace('drop ','',$paramwhere);
							$paramwhere=str_replace('select ','',$paramwhere);
							$paramwhere=str_replace('delete ','',$paramwhere);
							$paramwhere=str_replace('update ','',$paramwhere);
							$paramwhere=str_replace('insert ','',$paramwhere);

							//Parse using layout, has no effect to layout itself
							$this->LayoutProc->layout=$paramwhere;
							$this->filter=$this->LayoutProc->fillLayout(array(),null,'');

						}
				}//if(!$this->blockExternalVars)

				$this->filtering= new ESFiltering;
				$this->filtering->langpostfix=$this->langpostfix;
				$this->filtering->es=$this->es;
				$this->filtering->esfields=$this->esfields;
				$this->filtering->estable='#__customtables_table_'.$this->establename;


				if($this->params->get( 'showcartitemsonly' )!='')
						$this->showcartitemsonly=(int)$this->params->get( 'showcartitemsonly' );
				else
						$this->showcartitemsonly=false;


				if($this->params->get( 'showcartitemsprefix' )!='')
						$this->showcartitemsprefix=$this->params->get( 'showcartitemsprefix' );
				else
						$this->showcartitemsprefix='';


				$this->prepareSEFLinkBase();
		}
		
		function applyLimits()
		{
			$mainframe = JFactory::getApplication('site');
			$jinput=JFactory::getApplication()->input;
			
			if($this->frmt!='html')
			{
				//export all records if firmat is csv, xml etc.
				$this->limit=0;
				$this->setState('limit', $this->limit);
				$this->limitstart=0;
				return;
			}
			
			if($this->blockExternalVars)
			{
						if((int)$this->params->get( 'limit' )>0)
						{
							$limit=(int)$this->params->get( 'limit' );
							$this->limit=$limit;
							$this->setState('limit', $limit);
							$this->limitstart = $jinput->getInt('start',0);
							$this->limitstart = ($limit != 0 ? (floor($this->limitstart / $limit) * $limit) : 0);
						}
						else
						{
							$this->setState('limit', 0);
							$this->limit=0;
							$this->limitstart=0;
						}


				}
				else
				{
								$this->limitstart = $jinput->getInt('start',0);

								if((int)$this->params->get( 'limit' )>0)
								{
										$limit=(int)$this->params->get( 'limit' );
										$this->limit=$limit;

										$this->setState('limit', $limit);
								}
								else
								{
										$limit = $mainframe->getUserStateFromRequest('global.list.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
										$this->limit=$limit;
										$this->setState('limit', $limit);



								}
								// In case limit has been changed, adjust it
								$this->limitstart = ($limit != 0 ? (floor($this->limitstart / $limit) * $limit) : 0);

				}//if($this->blockExternalVars)
		}

		function getOrderBox()//$SelectedCategory
		{
				$result='<select name="esordering" id="esordering" onChange="this.form.submit()" class="inputbox">
';

				for($i=0;$i<count($this->order_values);$i++)
				{

						$result.='<option value="'.$this->order_values[$i].'" '.($this->esordering==$this->order_values[$i] ? ' selected ' : '').'>'.$this->order_list[$i].'</option>
						';
				}
				$result.='</select>
				';
				return $result;
		}

		function getPagination()
		{
				// Load the content if it doesn't already exist
				if (empty($this->_pagination))
				{


						require_once(JPATH_SITE.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_customtables'.DIRECTORY_SEPARATOR.'libraries'.DIRECTORY_SEPARATOR.'pagination.php');

						$a= new JESPagination($this->TotalRows, $this->limitstart, $this->getState('limit') );

						return $a;

				}
				return $this->_pagination;
		}

		function getAlphaWhere($alpha,&$wherearr)
		{
				if($this->blockExternalVars)
						return;

				$jinput = JFactory::getApplication()->input;
				$esfieldtype=$jinput->get('esfieldtype','','CMD');
				$esfieldname=$jinput->get('esfieldname','','CMD');

				if($esfieldtype!='customtables')
				{
						$fName=$esfieldname;
						if(!(strpos($esfieldname,'multi')===false))
							$fName.=$this->langpostfix;

						$wherearr[]='SUBSTRING(es_'.$fName.',1,1)="'.$alpha.'"';
				}
				else
				{
						$db = JFactory::getDBO();

						$parentid=$this->es->getOptionIdFull($jinput->get('optionname','','STRING'));


						$query = 'SELECT familytreestr, optionname '
								.' FROM #__customtables_options'
								.' WHERE INSTR(familytree,"-'.$parentid.'-") AND SUBSTRING(title'.$this->langpostfix.',1,1)="'.
								$jinput->get('alpha','','STRING').'"'
								.' ';

						$db->setQuery( $query );
						//if (!$db->query())    die ;
						//$rows=$db->loadAssocList();

						$wherelist=array();
						foreach($rows as $row)
						{

								if($row['familytreestr'])
										$a=$row['familytreestr'].'.'.$row['optionname'];
								else
										$a=$row['optionname'];

								if(!in_array($a,$wherelist))
										$wherelist[]=$a;
						}

						$wherearr_=array();
						foreach($wherelist as $row)
						{

								$wherearr_[]='instr(es_'.$jinput->getCMD('esfieldname','').',"'.$row.'")';
						}
						$wherearr[]=' ('.implode(' OR ',$wherearr_).')';
				}

		}



	function getSearchResult($addition_filter='')
	{

			$this->PathValue='';
			$jinput = JFactory::getApplication()->input;

			if(!isset($this->estableid))
				return array();



		$this->TotalRows=0;
		$db= JFactory::getDBO();
		$wherearr=array();

		$PathValue=array();

		if($this->showpublished==1)
				$wherearr[]= '#__customtables_table_'.$this->establename.'.published=0';
		elseif($this->showpublished!=2)
				$wherearr[]= '#__customtables_table_'.$this->establename.'.published=1';


		if($this->layout=='currentuser' or $this->layout=='customcurrentuser')
		{
				if($this->useridfieldname!='')
				{
						$user = JFactory::getUser();
						$wherearr[]= 'es_'.$this->useridfieldname.'='.(int)$user->get('id');
				}

		}

		$moduleid=$jinput->get('moduleid',0,'INT');

		if(!$this->blockExternalVars)
		{
				if($moduleid!=0)
				{

					$eskeysearch_=$jinput->get('eskeysearch_'.$moduleid,'','STRING');
					if($eskeysearch_!='')
					{

								require_once(JPATH_SITE.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_customtables'.DIRECTORY_SEPARATOR.'libraries'.DIRECTORY_SEPARATOR.'keywordsearch.php');

								$KeywordSearcher=new CustomTablesKeywordSearch;

								$KeywordSearcher->groupby=$this->groupby;
								$KeywordSearcher->esordering=$this->esordering;
								$KeywordSearcher->langpostfix=$this->langpostfix;
								$KeywordSearcher->establename=$this->establename;
								$KeywordSearcher->esfields=$this->esfields;


								$result_rows=$KeywordSearcher->getRowsByKeywords(
																	  $eskeysearch_,
																	  $PathValue,
																	  $TotalRows,
																	  (int)$this->getState('limit'),
																	  $this->limitstart

																	  );
								$this->TotalRows=$TotalRows;


								if($TotalRows<$this->limitstart )
								{
										$this->limitstart=0;
								}

								return $result_rows;
						}
						elseif($jinput->get('alpha','','STRING')!='')
						{
							$this->getAlphaWhere($jinput->get('alpha','','STRING'),$wherearr);
						}

				}//if($moduleid!=0)
		}//if(!$this->blockExternalVars)


		$paramwhere=$this->filtering->getWhereExpression($this->filterparam,$PathValue);

		if($addition_filter!='')
				$wherearr[]=$addition_filter;

				if($paramwhere!='')
						$wherearr[]=' ('.$paramwhere.' )';


		if($this->filter!='' and !$this->blockExternalVars)
		{
				$paramwhere=$this->filter;

				$paramwhere=$this->filtering->getWhereExpression($paramwhere,$PathValue);

				if($paramwhere!='')
						$wherearr[]=' ('.$paramwhere.' )';

		}


		$tablename='#__customtables_table_'.$this->establename;

		//Shopping Cart

		if($this->showcartitemsonly)
		{
				$jinput = JFactory::getApplication()->input;
				$cookieValue = $jinput->cookie->get('es_'.$this->showcartitemsprefix.'_'.$this->establename);

				if (isset($cookieValue))
				{

						if($cookieValue=='')
								$wherearr[]=$tablename.'.id=0';
						else
						{
								$items=explode(';',$cookieValue);
								$warr=array();
								foreach($items as $item)
								{
										$pair=explode(',',$item);
										$warr[]=$tablename.'.id='.$pair[0];
								}


								$wherearr[]='('.implode(' OR ', $warr).')';
						}
				}
				else
						$wherearr[]=$tablename.'.id=0';
		}


		if(count($wherearr)>0)
				$where = ' WHERE '.implode(" AND ",$wherearr);
		else
				$where='';

		$where=str_replace('\\','',$where);

		$inner=array();

		//to fullfill the "Clear" task
		if($jinput->get('task','','CMD')=='clear')
		{
			$cQuery='DELETE FROM '.$tablename.' '.$where;
			$db->setQuery($cQuery);
			$db->execute();

			return true;
		}

		$ordering=array();
		
		if($this->groupby!='')
				$ordering[]='es_'.$this->groupby;


		if($this->esordering)
			CTOrdering::getOrderingQuery($ordering,$query,$inner,$this->esordering,$this->langpostfix,$tablename);

		$query_selects='*, '.$tablename.'.id  as  listing_id, '.$tablename.'.published AS listing_published';
		$query='SELECT '.$query_selects.' FROM '.$tablename.' ';
		$query.=implode(' ',$inner).' ';
		$query.=$where.' ';
		$query.=' GROUP BY listing_id ';

		$query_analytical='SELECT COUNT(id) AS count FROM '.$tablename.' '.$where;

		if(count($ordering)>0)
			$query.=' ORDER BY '.implode(',',$ordering);

		$db->setQuery($query_analytical);
		$rows=$db->loadObjectList();
		if(count($rows)==0)
			$this->TotalRows=0;
		else
			$this->TotalRows=$rows[0]->count;
		
		$this->recordlist=array();
		
		if($this->TotalRows>0)
		{
			$the_limit=(int)$this->getState('limit');
			if($the_limit>20000)
				$the_limit=20000;

			if($the_limit==0)
				$the_limit=20000; //or we will run out of memory
				
			$this->limit=$the_limit;

			if(!$this->blockExternalVars and $the_limit!=0)
			{
				if($this->TotalRows<$this->limitstart or $this->TotalRows<$the_limit)
					$this->limitstart=0;

				$db->setQuery($query, $this->limitstart, $the_limit);
			}
			else
			{
				if($the_limit>0)
					$db->setQuery($query, 0, $the_limit);
			}

			$rows=$db->loadAssocList();
			
			foreach($rows as $row)
				$this->recordlist[]=$row['id'];
		}
		else
			$rows=array();
		
		$this->LayoutProc->recordlist=implode(',',$this->recordlist);

		$this->PathValue=$PathValue;

		return $rows;
	}


		function cart_emptycart()
		{
				$app = JFactory::getApplication();
				$jinput = $app->input;
				$cart_prefix=$jinput->get('cartprefix','','CMD');



				$jinput->cookie->set('es_'.$cart_prefix.'_'.$this->establename, '', time()-3600, $app->get('cookie_path', '/'), $app->get('cookie_domain'), $app->isSSLConnection());

				return true;
		}

		function cart_deleteitem()
		{
				$jinput = JFactory::getApplication()->input;
				if($jinput->get('listing_id',0,'INT')==0)
						return false;

				$this->cart_setitemcount(0);
				return true;
		}

		function cart_form_addtocart($itemcount=-1)
		{
				$jinput=JFactory::getApplication()->input;

				if(!$jinput->get('listing_id',0,'INT'))
						return false;

				$objectid=$jinput->get('listing_id',0,'INT');

				$cart_prefix=$jinput->get('cartprefix','','CMD');

				if($itemcount==-1)
				{
					$itemcount=$jinput->get('itemcount',0,'INT');
				}

				$app = JFactory::getApplication();
				$cookieValue = $app->input->cookie->get('es_'.$cart_prefix.'_'.$this->establename);

				if (isset($cookieValue))
				{
						$items=explode(';',$cookieValue);
						$cnt=count($items);
						$found=false;
						for($i=0;$i<$cnt;$i++)
						{
								$pair=explode(',',$items[$i]);
								if(count($pair)!=2)
										unset($items[$i]); //delete the shit
								else
								{
										if((int)$pair[0]==$objectid)
										{
												$new_itemcount=(int)$pair[1]+$itemcount;
												if($new_itemcount==0)
												{
														unset($items[$i]); //delete item
														$found=true;
												}
												else
												{
														//update counter
														$pair[1]=$new_itemcount;
														$items[$i]=implode(',',$pair);
														$found=true;
												}
										}
								}
						}//for

						if(!$found)
								$items[]=$objectid.','.$itemcount; // add new item

						$items=array_values($items);
				}
				else
						$items=array($objectid.','.$itemcount); //add new

				$nc=implode(';',$items);
				setcookie('es_'.$cart_prefix.'_'.$this->establename, $nc, time()+3600*24);

				return true;
		}

		function cart_setitemcount($itemcount=-1)
		{

				$jinput = JFactory::getApplication()->input;

				if(!$jinput->get('listing_id',0,'INT'))
						return false;

				$objectid=$jinput->get('listing_id',0,'INT');


				$cart_prefix=$jinput->get('cartprefix','','CMD');

				$app = JFactory::getApplication();


				if($itemcount==-1)
				{
						$itemcount=$jinput->getInt('itemcount',0);
				}

				$cookieValue = $app->input->cookie->get('es_'.$cart_prefix.'_'.$this->establename);

				if (isset($cookieValue))
				{
						$items=explode(';',$cookieValue);
						$cnt=count($items);
						$found=false;
						for($i=0;$i<$cnt;$i++)
						{
								$pair=explode(',',$items[$i]);
								if(count($pair)!=2)
										unset($items[$i]); //delete the shit
								else
								{
										if((int)$pair[0]==$objectid)
										{
												if($itemcount==0)
												{
														unset($items[$i]); //delete item
														$found=true;
												}
												else
												{
														//update counter
														$pair[1]=$itemcount;
														$items[$i]=implode(',',$pair);
														$found=true;
												}
										}
								}
						}//for

						if(!$found)
								$items[]=$objectid.','.$itemcount; // add new item

						$items=array_values($items);
				}
				else
						$items=array($objectid.','.$itemcount); //add new

				$nc=implode(';',$items);
				setcookie('es_'.$cart_prefix.'_'.$this->establename, $nc, time()+3600*24);

				return true;
		}

		function cart_addtocart()
		{
				$app = JFactory::getApplication();

				$jinput=$app->input;

				if(!$jinput->get('listing_id',0,'INT'))
						return false;

				$objectid=$jinput->get('listing_id',0,'INT');

				$cart_prefix=$jinput->get('cartprefix','','CMD');


				$cookieValue = $app->input->cookie->get('es_'.$cart_prefix.'_'.$this->establename);

				if (isset($cookieValue))
				{
						$items=explode(';',$cookieValue);
						$cnt=count($items);
						$found=false;
						for($i=0;$i<$cnt;$i++)
						{
								$pair=explode(',',$items[$i]);
								if(count($pair)!=2)
										unset($items[$i]); //delete the shit
								else
								{
										if((int)$pair[0]==$objectid)
										{
												//update counter
												$pair[1]=((int)$pair[1])+1;
												$items[$i]=implode(',',$pair);
												$found=true;
										}
								}
						}//for

						if(!$found)
							$items[]=$objectid.',1'; // add new item

						$items=array_values($items);
				}
				else
						$items=array($objectid.',1'); //add new

				$nc=implode(';',$items);

				$app->input->cookie->set('es_'.$cart_prefix.'_'.$this->establename, $nc, time()+3600*24, $app->get('cookie_path', '/'), $app->get('cookie_domain'), $app->isSSLConnection());

				return true;
		}

/*
		function doCustomPHP()
		{
			if($this->tablecustomphp!='')
			{
				$funcltionname='ESCustom_'.str_replace('.php','',$this->tablecustomphp);

				require_once(JPATH_SITE.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_customtables'.DIRECTORY_SEPARATOR.'customphp'.DIRECTORY_SEPARATOR.$this->tablecustomphp);

				$row1=array();
				$row2=array();
				call_user_func($funcltionname,$row1,$row2);
			}
			return true;
		}
		*/

		function CleanUpPath($thePath)
		{
				$newPath=array();
				if(count($thePath)==0)
						return $newPath;

				for($i=count($thePath)-1;$i>=0;$i--)
				{
						$item=$thePath[$i];
						if(count($newPath)==0)
								$newPath[]=$item;
						else
						{
								$found=false;
								foreach($newPath as $newitem)
								{

										if(!(strpos($newitem,$item)===false))
										{
												$found=true;
												break;
										}
								}

								if(!$found)
										$newPath[]=$item;
						}
				}

				return array_reverse ($newPath);
		}

		function FindItemidbyAlias($alias)
		{
			$db = JFactory::getDBO();
			$query = 'SELECT id FROM #__menu WHERE alias='.$db->Quote($alias);

			$db->setQuery( $query );
			//if (!$db->query())    die( $db->stderr());
			$recs = $db->loadAssocList( );
			if(!$recs) return 0;
			if (count($recs)<1) return 0;

			$r=$recs[0];
			return $r['id'];
		}
}
