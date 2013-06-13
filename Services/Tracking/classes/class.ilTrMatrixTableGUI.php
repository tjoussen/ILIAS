<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/Tracking/classes/class.ilLPTableBaseGUI.php");

/**
 * name table
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @version $Id$
 *
 * @ingroup Services
 */
class ilTrMatrixTableGUI extends ilLPTableBaseGUI
{
	protected $obj_ids = NULL;
	protected $objective_ids = NULL;
	protected $sco_ids = NULL;
	protected $subitem_ids = NULL;
	protected $in_course; // int
	protected $in_group; // int
	protected $privacy_fields; // array

	/**
	 * Constructor
	 */
	function __construct($a_parent_obj, $a_parent_cmd, $ref_id)
	{
		global $ilCtrl, $lng, $tree;

		$this->setId("trsmtx_".$ref_id);
		$this->ref_id = $ref_id;
		$this->obj_id = ilObject::_lookupObjId($ref_id);
		
		$this->in_course = $tree->checkForParentType($this->ref_id, "crs");
		if($this->in_course)
		{
			$this->in_course = ilObject::_lookupObjId($this->in_course);
		}
		else
		{
			$this->in_group = $tree->checkForParentType($this->ref_id, "grp");
			if($this->in_group)
			{
				$this->in_group = ilObject::_lookupObjId($this->in_group);
			}
		}

		$this->initFilter();

		parent::__construct($a_parent_obj, $a_parent_cmd);
		$this->setLimit(9999);
		$this->parseTitle($this->obj_id, "trac_matrix");
	
		$this->setEnableHeader(true);
		$this->setFormAction($ilCtrl->getFormActionByClass(get_class($this)));
		$this->setRowTemplate("tpl.user_object_matrix_row.html", "Services/Tracking");
		$this->setDefaultOrderField("login");
		$this->setDefaultOrderDirection("asc");
		$this->setShowTemplates(true);

		$this->addColumn($this->lng->txt("login"), "login");

		$labels = $this->getSelectableColumns();
		$selected = $this->getSelectedColumns();
		foreach ($selected as $c)
		{
			$title = $labels[$c]["txt"];
			$tooltip = "";
			if(isset($labels[$c]["icon"]))
			{
				$alt = $lng->txt($labels[$c]["type"]);
				$icon = '<img src="'.$labels[$c]["icon"].'" alt="'.$alt.'" />';
				if(sizeof($selected) > 5)
				{
					$tooltip = $title;
					$title = $icon;
				}
				else
				{
					$title = $icon.' '.$title;
				}
			}
			
			if(isset($labels[$c]["id"]))
			{
				$sort_id = $labels[$c]["id"];
			}
			else
			{
				// list cannot be sorted by udf fields (separate query)
				$sort_id = (substr($c, 0, 4) == "udf_") ? "" : $c;
			}
			
			$this->addColumn($title, $sort_id, "", false, "", $tooltip);
		}
		
		$this->setExportFormats(array(self::EXPORT_CSV, self::EXPORT_EXCEL));
	}

	function initFilter()
    {
		global $lng;

		$item = $this->addFilterItemByMetaType("name", ilTable2GUI::FILTER_TEXT);
		$this->filter["name"] = $item->getValue();
	}

	function getSelectableColumns()
	{
		global $ilObjDataCache;
		
		$user_cols = $this->getSelectableUserColumns($this->in_course, $this->in_group);
		
		if($this->obj_ids === NULL)
		{
			// we cannot use the selected columns because they are not ready yet
			// so we use all available columns, should be ok anyways
			$this->obj_ids = $this->getItems(array_keys($user_cols[0]), $user_cols[1]);
		}
		if($this->obj_ids)
		{
			$tmp_cols = array();
			foreach($this->obj_ids as $obj_id)
			{
				if($obj_id == $this->obj_id)
				{
					$parent = array("txt" => $this->lng->txt("status"),
						"default" => true);
				}
				else
				{
					$title = $ilObjDataCache->lookupTitle($obj_id);
					$type = $ilObjDataCache->lookupType($obj_id);
					$icon = ilObject::_getIcon("", "tiny", $type);
					if($type == "sess")
					{
						include_once "Modules/Session/classes/class.ilObjSession.php";
						$sess = new ilObjSession($obj_id, false);
						$title = $sess->getPresentationTitle();
					}
					$tmp_cols[strtolower($title)."#~#obj_".$obj_id] = array("txt" => $title, "icon" => $icon, "type" => $type, "default" => true);
				}
			}
			if(sizeof($this->objective_ids))
			{
				foreach($this->objective_ids as $obj_id => $title)
				{
					$tmp_cols[strtolower($title)."#~#objtv_".$obj_id] = array("txt" => $title, "default" => true);
				}
			}
			if(sizeof($this->sco_ids))
			{
				foreach($this->sco_ids as $obj_id => $title)
				{
					$icon = ilUtil::getTypeIconPath("sco", $obj_id, "tiny");
					$tmp_cols[strtolower($title)."#~#objsco_".$obj_id] = array("txt" => $title, "icon"=>$icon, "default" => true);
				}
			}
			if(sizeof($this->subitem_ids))
			{
				foreach($this->subitem_ids as $obj_id => $title)
				{
					$icon = ilUtil::getTypeIconPath("st", $obj_id, "tiny");
					$tmp_cols[strtolower($title)."#~#objsub_".$obj_id] = array("txt" => $title, "icon"=>$icon, "default" => true);
				}
			}

			// alex, 5 Nov 2011: Do not sort SCORM items or "chapters"
			if(!sizeof($this->sco_ids) && !sizeof($this->subitem_ids))
			{
				ksort($tmp_cols);
			}
			foreach($tmp_cols as $id => $def)
			{
				$id = explode('#~#', $id);
				$columns[$id[1]] = $def;
			}
			unset($tmp_cols);

			if($parent)
			{
				$columns["obj_".$this->obj_id] = $parent;
			}
		}

		unset($user_cols[0]["status"]);
		unset($user_cols[0]["login"]);
		foreach($user_cols[0] as $col_id => $col_def)
		{
			if(!isset($columns[$col_id]))
			{
				// these are all additional fields, no default
				$col_def["default"] = false;
				$columns[$col_id] = $col_def;
			}
		}
		
		return $columns;
	}

	function getItems(array $a_user_fields, array $a_privary_fields = null)
	{		
		include_once("./Services/Tracking/classes/class.ilTrQuery.php");
		$collection = ilTrQuery::getObjectIds($this->obj_id, $this->ref_id, true);
		if($collection["object_ids"])
		{
			// we need these for the timing warnings
			$this->ref_ids = $collection["ref_ids"];

			// only if object is [part of] course/group
			$check_agreement = false;
			if($this->in_course)
			{
				// privacy (if course agreement is activated)
				include_once "Services/PrivacySecurity/classes/class.ilPrivacySettings.php";
				$privacy = ilPrivacySettings::_getInstance();
				if($privacy->courseConfirmationRequired())
				{
					$check_agreement = $this->in_course;
				}
			}
			else if($this->in_group)
			{
				// privacy (if group agreement is activated)
				include_once "Services/PrivacySecurity/classes/class.ilPrivacySettings.php";
				$privacy = ilPrivacySettings::_getInstance();
				if($privacy->groupConfirmationRequired())
				{
					$check_agreement = $this->in_group;
				}
			}
			
			$data = ilTrQuery::getUserObjectMatrix($this->ref_id, $collection["object_ids"], $this->filter["name"], $a_user_fields, $a_privary_fields, $check_agreement);
			if($collection["objectives_parent_id"] && $data["users"])
			{
				$objectives = ilTrQuery::getUserObjectiveMatrix($collection["objectives_parent_id"], $data["users"]);
				if($objectives["cnt"])
				{
					$this->objective_ids = array();
					$objective_columns = array();
					foreach($objectives["set"] as $row)
					{
						if(isset($data["set"][$row["usr_id"]]))
						{
							$obj_id = "objtv_".$row["obj_id"];
							$data["set"][$row["usr_id"]]["objects"][$obj_id] = array("status"=>$row["status"]);

							if(!in_array($obj_id, $this->objective_ids))
							{
								$this->objective_ids[$obj_id] = $row["title"];
							}
						}
					}
				}
			}

			if($collection["scorm"] && $data["set"])
			{
				$this->sco_ids = array();
				foreach(array_keys($data["set"]) as $user_id)
				{
					foreach($collection["scorm"]["scos"] as $sco)
					{
						if(!in_array($sco, $this->sco_ids))
						{
							$this->sco_ids[$sco] = $collection["scorm"]["scos_title"][$sco];
						}

						// alex, 5 Nov 2011: we got users being in failed and in
						// completed status, I changed the setting in: first check failed
						// then check completed since failed should superseed completed
						// (before completed has been checked before failed)
						$status = LP_STATUS_NOT_ATTEMPTED_NUM;
						if(in_array($user_id, $collection["scorm"]["failed"][$sco]))
						{
							$status = LP_STATUS_FAILED_NUM;
						}
						else if(in_array($user_id, $collection["scorm"]["completed"][$sco]))
						{
							$status = LP_STATUS_COMPLETED_NUM;
						}
						else if(in_array($user_id, $collection["scorm"]["in_progress"][$sco]))
						{
							$status = LP_STATUS_IN_PROGRESS_NUM;
						}

						$obj_id = "objsco_".$sco;
						$data["set"][$user_id]["objects"][$obj_id] = array("status"=>$status);
					}
				}
			}
			
			if($collection["subitems"] && $data["set"])
			{				
				foreach(array_keys($data["set"]) as $user_id)
				{
					foreach($collection["subitems"]["items"] as $item_id)
					{
						$this->subitem_ids[$item_id] = $collection["subitems"]["item_titles"][$item_id];
						
						$status = LP_STATUS_NOT_ATTEMPTED_NUM;
						if(in_array($user_id, $collection["subitems"]["completed"][$item_id]))
						{
							$status = LP_STATUS_COMPLETED_NUM;
						}
						else if(is_array($collection["subitems"]["in_progress"]) &&
							in_array($user_id, $collection["subitems"]["in_progress"][$item_id]))
						{
							$status = LP_STATUS_IN_PROGRESS_NUM;
						}			
						
						$obj_id = "objsub_".$item_id;
						$data["set"][$user_id]["objects"][$obj_id] = array("status"=>$status);
					}				
				}
			}

			$this->setMaxCount($data["cnt"]);
			$this->setData($data["set"]);
//var_dump($this->sco_ids);
			return $collection["object_ids"];
		}
		return false;
	}

	function fillRow(array $a_set)
	{
		$this->tpl->setVariable("VAL_LOGIN", $a_set["login"]);
		foreach ($this->getSelectedColumns() as $c)
		{
			switch($c)
			{				
				case (substr($c, 0, 4) == "obj_"):
					$obj_id = substr($c, 4);
					if(!isset($a_set["objects"][$obj_id]))
					{
						$data = array("status"=>0);
					}
					else
					{
						$data = $a_set["objects"][$obj_id];
						if($data["percentage"] == "0")
						{
							$data["percentage"] = NULL;
						}
					}

					if($data['status'] != LP_STATUS_COMPLETED_NUM)
					{
						$timing = $this->showTimingsWarning($this->ref_ids[$obj_id], $a_set["usr_id"]);
						if($timing)
						{
							if($timing !== true)
							{
								$timing = ": ".ilDatePresentation::formatDate(new ilDate($timing, IL_CAL_UNIX));
							}
							else
							{
								$timing = "";
							}
							$this->tpl->setCurrentBlock('warning_img');
							$this->tpl->setVariable('WARNING_IMG', ilUtil::getImagePath('time_warn.png'));
							$this->tpl->setVariable('WARNING_ALT', $this->lng->txt('trac_time_passed').$timing);
							$this->tpl->parseCurrentBlock();
						}
					}

					$this->tpl->setCurrentBlock("objects");
					$this->tpl->setVariable("VAL_STATUS", $this->parseValue("status", $data["status"], ""));
					$this->tpl->setVariable("VAL_PERCENTAGE", $this->parseValue("percentage", $data["percentage"], ""));
					$this->tpl->parseCurrentBlock();
					break;


				case (substr($c, 0, 6) == "objtv_"):
				case (substr($c, 0, 7) == "objsco_"):
				case (substr($c, 0, 7) == "objsub_"):		
					$obj_id = $c;
					if(!isset($a_set["objects"][$obj_id]))
					{
						$data = array("status"=>0);
					}
					else
					{
						$data = $a_set["objects"][$obj_id];
					}
					$this->tpl->setCurrentBlock("objects");
					$this->tpl->setVariable("VAL_STATUS", $this->parseValue("status", $data["status"], ""));
					$this->tpl->parseCurrentBlock();
					break;
					
				default:
					$this->tpl->setCurrentBlock("user_field");
					$this->tpl->setVariable("VAL_UF", $this->parseValue($c, $a_set[$c], ""));
					$this->tpl->parseCurrentBlock();
					break;
			}
		}
	}

	protected function fillHeaderExcel($worksheet, &$a_row)
	{
		global $ilObjDataCache;
		
		$worksheet->write($a_row, 0, $this->lng->txt("login"));

		$labels = $this->getSelectableColumns();
		$cnt = 1;
		foreach ($this->getSelectedColumns() as $c)
		{
			if(substr($c, 0, 4) == "obj_")
			{
				$obj_id = substr($c, 4);
				$type = $ilObjDataCache->lookupType($obj_id);
				$worksheet->write($a_row, $cnt, "(".$this->lng->txt($type).") ".$labels[$c]["txt"]);
			}
			else
			{
				$worksheet->write($a_row, $cnt, $labels[$c]["txt"]);
			}
			$cnt++;
		}
	}

	protected function fillRowExcel($worksheet, &$a_row, $a_set)
	{
		$worksheet->write($a_row, 0, $a_set["login"]);

		$cnt = 1;
		foreach ($this->getSelectedColumns() as $c)
		{
			switch($c)
			{
				case "last_access":
				case "spent_seconds":
				case "status_changed":
					$val = $this->parseValue($c, $a_set[$c], "user");
					break;
					
				case (substr($c, 0, 4) == "obj_"):
					$obj_id = substr($c, 4);
					$val = ilLearningProgressBaseGUI::_getStatusText((int)$a_set["objects"][$obj_id]["status"]);
					break;
				
				case (substr($c, 0, 6) == "objtv_"):
				case (substr($c, 0, 7) == "objsco_"):
				case (substr($c, 0, 7) == "objsub_"):
					$obj_id = $c;
					$val = ilLearningProgressBaseGUI::_getStatusText((int)$a_set["objects"][$obj_id]["status"]);
					break;										
			}
			$worksheet->write($a_row, $cnt, $val);
			$cnt++;
		}
	}

	protected function fillHeaderCSV($a_csv)
	{
		global $ilObjDataCache;
		
		$a_csv->addColumn($this->lng->txt("login"));

		$labels = $this->getSelectableColumns();
		foreach ($this->getSelectedColumns() as $c)
		{
			if(substr($c, 0, 4) == "obj_")
			{
				$obj_id = substr($c, 4);
				$type = $ilObjDataCache->lookupType($obj_id);
				$a_csv->addColumn("(".$this->lng->txt($type).") ".$labels[$c]["txt"]);
			}
			else
			{
				$a_csv->addColumn($labels[$c]["txt"]);
			}
		}

		$a_csv->addRow();
	}

	protected function fillRowCSV($a_csv, $a_set)
	{
		$a_csv->addColumn($a_set["login"]);

		foreach ($this->getSelectedColumns() as $c)
		{
			switch($c)
			{
				case "last_access":
				case "spent_seconds":
				case "status_changed":
					$val = $this->parseValue($c, $a_set[$c], "user");
					break;
					
				case (substr($c, 0, 4) == "obj_"):
					$obj_id = substr($c, 4);
					$val = ilLearningProgressBaseGUI::_getStatusText((int)$a_set["objects"][$obj_id]["status"]);
					break;
				
				case (substr($c, 0, 6) == "objtv_"):
				case (substr($c, 0, 7) == "objsco_"):
				case (substr($c, 0, 7) == "objsub_"):
					$obj_id = $c;
					$val = ilLearningProgressBaseGUI::_getStatusText((int)$a_set["objects"][$obj_id]["status"]);
					break;										
			}
			$a_csv->addColumn($val);
		}

		$a_csv->addRow();
	}
}

?>