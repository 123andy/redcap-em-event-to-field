<?php
namespace Stanford\EventToField;

class EventToField extends \ExternalModules\AbstractExternalModule {
    private $field;

    function hook_save_record($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance = 1)
    {
        $this->update($project_id, $record,$instrument,$event_id);
    }

    function update($project_id, $record, $instrument, $event_id) {
        $triggering_form 			= $this->getProjectSetting('triggering_form');
        $triggering_event_id 		= $this->getProjectSetting('triggering_event_id');

        $update_fields = array(
        	//type						=> field_name
			'event_id_field'			=> $this->getProjectSetting('event_id_field'),
			'event_name_field'			=> $this->getProjectSetting('event_name_field'),
			'event_description_field'	=> $this->getProjectSetting('event_description_field'),
			'arm_name_field'			=> $this->getProjectSetting('arm_name_field')
		);

		// Don't do anything if the triggering_form is set and the $triggering_form don't match the instrument
		if (!empty($triggering_form) and ($triggering_form != $instrument)) return;

		// Don't do anything if the event_triggering_form is set and the events don't match
		if (!empty($triggering_event_id) and ($triggering_event_id != $event_id)) return;

		// Remove any empty setting fields
		$fields = array_filter( $update_fields );

		// Don't do anything if there are no fields specified (e.g. the EM is not properly configured)
		if (count($fields) == 0) return;

		// Query the current data - unfortunately this is more complex than it would seem since the current data doesn't include empty events.
		// Ideally, there would be a way to tell getData to include data for all potential events - whether they were set or not, but this isn't yet available.
		$before = \REDCap::getData($project_id, 'array', $record, $fields);
		// \Plugin::log($before, "DEBUG", "BEFORE");

		// Make a copy of the original data for updates
		$update = $before;

		// For each field, determine in what forms/events it is enabled and set the proper value
		global $Proj;

		//
		$field_to_form = array();
		foreach($fields as $field) {
			$form_name = $Proj->metadata[$field]['form_name'];
			$field_to_form[$field] = $form_name;
		}

		// Loop through all events in the current arm:
		$arm_num = $Proj->eventInfo[$event_id]['arm_num'];

		foreach($Proj->eventsForms as $this_event_id => $event_forms) {

			// Make sure the this event is part of the current record arm that was saved
			$this_arm_num = $Proj->eventInfo[$this_event_id]['arm_num'];
			if ($arm_num != $this_arm_num) continue;

			// Get the information for setting the setting fields
			$this_arm_name = $Proj->eventInfo[$event_id]['arm_name'];
			$this_event_name = \REDCap::getEventNames(true,true,$this_event_id);
			$this_event_desc = \REDCap::getEventNames(false,false,$this_event_id);

			// Loop through each potential update field and check to see if the field is in the current event
			foreach($fields as $type => $field_name) {

				$field_form = $field_to_form[$field_name];
				// \Plugin::log("In event $this_event_id - field $field_name in form $form_name", "DEBUG");

				if(in_array($field_form, $event_forms)) {
					// \Plugin::log("$field_name is a part of " . json_encode($event_forms), "DEBUG");
					switch ($type) {
						case 'event_id_field':
							$update[$record][$this_event_id][$field_name] = $this_event_id;
							break;
						case 'event_name_field':
							$update[$record][$this_event_id][$field_name] = $this_event_name;
							break;
						case 'event_description_field':
							$update[$record][$this_event_id][$field_name] = $this_event_desc;
							break;
						case 'arm_name_field':
							$update[$record][$this_event_id][$field_name] = $this_arm_name;
							break;
					}
				}
			}
		}
		// \Plugin::log($update, "DEBUG", "AFTER");

		// Don't do anything if there isn't anything to be done!
		if ($before == $update) {
			// \Plugin::log("No need to update!", "DEBUG");
			return;
		}

		// Save the updated data
		$result = \REDCap::saveData('array',$update);
		// \Plugin::log($result, "DEBUG", "SAVE");

		// Record to the log
		if (!empty($result['errors'])) {
			\REDCap::logEvent("Error in " . $this->getModuleName(), json_encode($result['errors']), "", $record, $event_id, $project_id);
		} else {
			\REDCap::logEvent("Event/Arm Fields Set by " . $this->getModuleName(), "", "", $record, $event_id, $project_id);
		}
    }
}

