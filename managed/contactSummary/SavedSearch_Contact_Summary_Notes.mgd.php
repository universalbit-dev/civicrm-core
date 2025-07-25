<?php

return [
  [
    'name' => 'SavedSearch_Contact_Summary_Notes',
    'entity' => 'SavedSearch',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Contact_Summary_Notes',
        'label' => ts('Contact Summary Notes'),
        'api_entity' => 'Note',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
            'subject',
            'note',
            'note_date',
            'modified_date',
            'contact_id.sort_name',
            'GROUP_CONCAT(UNIQUE Note_EntityFile_File_01.file_name) AS GROUP_CONCAT_Note_EntityFile_File_01_file_name',
          ],
          'orderBy' => [],
          'where' => [],
          'groupBy' => [
            'id',
          ],
          'join' => [
            [
              'File AS Note_EntityFile_File_01',
              'LEFT',
              'EntityFile',
              [
                'id',
                '=',
                'Note_EntityFile_File_01.entity_id',
              ],
              [
                'Note_EntityFile_File_01.entity_table',
                '=',
                "'civicrm_note'",
              ],
            ],
          ],
          'having' => [],
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'SavedSearch_Contact_Summary_Notes_SearchDisplay_Contact_Summary_Notes_Tab',
    'entity' => 'SearchDisplay',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Contact_Summary_Notes_Tab',
        'label' => ts('Contact Summary Notes Tab'),
        'saved_search_id.name' => 'Contact_Summary_Notes',
        'type' => 'table',
        'settings' => [
          'description' => NULL,
          'sort' => [
            [
              'note_date',
              'DESC',
            ],
          ],
          'limit' => 25,
          'pager' => [
            'hide_single' => TRUE,
            'show_count' => FALSE,
            'expose_limit' => TRUE,
          ],
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'subject',
              'label' => ts('Subject'),
              'sortable' => TRUE,
              'editable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'note',
              'label' => ts('Note'),
              'sortable' => TRUE,
              'show_linebreaks' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'note_date',
              'label' => ts('Note Date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'modified_date',
              'label' => ts('Modified'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'contact_id.sort_name',
              'label' => ts('Created By'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'GROUP_CONCAT_Note_EntityFile_File_01_file_name',
              'label' => ts('Attachments'),
              'sortable' => TRUE,
              'link' => [
                'path' => '[GROUP_CONCAT_Note_EntityFile_File_01_url]',
                'entity' => '',
                'action' => '',
                'join' => '',
                'target' => '',
              ],
              'icons' => [
                [
                  'field' => 'Note_EntityFile_File_01.icon',
                  'side' => 'left',
                ],
              ],
              'cssRules' => [
                [
                  'crm-image-popup',
                  'Note_EntityFile_File_01.is_image',
                  '=',
                  TRUE,
                ],
              ],
            ],
            [
              'size' => 'btn-xs',
              'label' => ts('Row Actions'),
              'label_hidden' => TRUE,
              'links' => [
                [
                  'icon' => 'fa-external-link',
                  'text' => ts('View'),
                  'style' => 'default',
                  'condition' => [],
                  'task' => '',
                  'entity' => 'Note',
                  'action' => 'view',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
                [
                  'icon' => 'fa-pencil',
                  'text' => ts('Edit'),
                  'style' => 'default',
                  'condition' => [],
                  'task' => '',
                  'entity' => 'Note',
                  'action' => 'update',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
                [
                  'icon' => 'fa-trash',
                  'text' => ts('Delete'),
                  'style' => 'danger',
                  'condition' => [],
                  'task' => '',
                  'entity' => 'Note',
                  'action' => 'delete',
                  'join' => '',
                  'target' => 'crm-popup',
                  'path' => '',
                ],
                [
                  'path' => 'civicrm/note?reset=1&action=add&entity_table=civicrm_note&entity_id=[id]',
                  'icon' => 'fa-comment-medical',
                  'text' => ts('Comment'),
                  'style' => 'success',
                  'condition' => [],
                  'task' => '',
                  'entity' => '',
                  'action' => '',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
              ],
              'type' => 'buttons',
              'alignment' => 'text-right',
            ],
          ],
          'actions' => FALSE,
          'classes' => [
            'table',
            'table-striped',
          ],
          'headerCount' => FALSE,
          'toolbar' => [
            [
              'text' => ts('Add Note'),
              'icon' => 'fa-plus',
              'style' => 'primary',
              'entity' => 'Note',
              'action' => 'add',
              'target' => 'crm-popup',
            ],
          ],
          'hierarchical' => TRUE,
          'collapsible' => 'closed',
        ],
        'acl_bypass' => FALSE,
      ],
      'match' => [
        'name',
        'saved_search_id',
      ],
    ],
  ],
];
