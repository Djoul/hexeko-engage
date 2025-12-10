<?php

return [
    'filters' => [
        'birthdate' => [
            'label' => 'Birthdate',
            'type' => 'date',
            'operators' => ['before', 'after', 'between', 'is_null', 'is_not_null'],
        ],
        'email' => [
            'label' => 'Email',
            'type' => 'string',
            'operators' => ['equals', 'not_equals', 'contains', 'starts_with', 'ends_with', 'is_null', 'is_not_null', 'in', 'not_in'],
        ],
        'first_name' => [
            'label' => 'First name',
            'type' => 'string',
            'operators' => ['equals', 'not_equals', 'contains', 'starts_with', 'ends_with', 'is_null', 'is_not_null'],
        ],
        'last_name' => [
            'label' => 'Last name',
            'type' => 'string',
            'operators' => ['equals', 'not_equals', 'contains', 'starts_with', 'ends_with', 'is_null', 'is_not_null'],
        ],
        'created_at' => [
            'label' => 'Created date',
            'type' => 'date',
            'operators' => ['before', 'after', 'between'],
        ],
        'last_login_at' => [
            'label' => 'Last login date',
            'type' => 'date',
            'operators' => ['before', 'after', 'between', 'is_null', 'is_not_null'],
        ],
        'email_verified_at' => [
            'label' => 'Email verified date',
            'type' => 'date',
            'operators' => ['before', 'after', 'between', 'is_null', 'is_not_null'],
        ],

        // RELATION FILTERS
        'departments' => [
            'label' => 'Departments',
            'type' => 'relation',
            'relation_type' => 'belongsToMany', // belongsTo, hasMany, belongsToMany, hasOne
            'relation_name' => 'departments', // Name of the relation in the User model
            'related_field' => 'id', // Field to filter in the related table (departments.id)
            'related_display_field' => 'name', // Field to display (departments.name)
            'operators' => ['in', 'not_in'],
        ],
        'managers' => [
            'label' => 'Managers',
            'type' => 'relation',
            'relation_type' => 'belongsToMany', // belongsTo, hasMany, belongsToMany, hasOne
            'relation_name' => 'managers', // Name of the relation in the User model
            'related_field' => 'id', // Field to filter in the related table (managers.id)
            'related_display_field' => 'name', // Field to display (managers.name)
            'operators' => ['in', 'not_in'],
        ],
        'sites' => [
            'label' => 'Sites',
            'type' => 'relation',
            'relation_type' => 'belongsToMany', // belongsTo, hasMany, belongsToMany, hasOne
            'relation_name' => 'sites', // Name of the relation in the User model
            'related_field' => 'id', // Field to filter in the related table (sites.id)
            'related_display_field' => 'name', // Field to display (sites.name)
            'operators' => ['in', 'not_in'],
        ],
        'contract_types' => [
            'label' => 'Contract types',
            'type' => 'relation',
            'relation_type' => 'belongsToMany', // belongsTo, hasMany, belongsToMany, hasOne
            'relation_name' => 'contractTypes', // Name of the relation in the User model
            'related_field' => 'id', // Field to filter in the related table (contract_types.id)
            'related_display_field' => 'name', // Field to display (contract_types.name)
            'operators' => ['in', 'not_in'],
        ],
        'tags' => [
            'label' => 'Tags',
            'type' => 'relation',
            'relation_type' => 'belongsToMany', // belongsTo, hasMany, belongsToMany, hasOne
            'relation_name' => 'tags', // Name of the relation in the User model
            'related_field' => 'id', // Field to filter in the related table (tags.id)
            'related_display_field' => 'name', // Field to display (tags.name)
            'operators' => ['in', 'not_in'],
        ],
        'financers.started_at' => [
            'label' => 'Start date of employment',
            'type' => 'relation_field',
            'relation_type' => 'belongsToMany', // belongsTo, hasMany, belongsToMany, hasOne
            'relation_name' => 'financers', // Name of the relation in the User model
            'related_field' => 'started_at', // Field to filter in the related table (financers.started_at)
            'related_display_field' => 'name', // Field to display (financers.name)
            'operators' => ['before', 'after', 'between', 'is_null', 'is_not_null'],
        ],
        'financers.work_mode' => [
            'label' => 'Work mode',
            'type' => 'relation_field',
            'relation_type' => 'belongsToMany', // belongsTo, hasMany, belongsToMany, hasOne
            'relation_name' => 'financers', // Name of the relation in the User model
            'related_field' => 'work_mode_id', // Field to filter in the related table (work_modes.id)
            'related_display_field' => 'name', // Field to display (work_modes.name)
            'operators' => ['equals', 'not_equals'],
        ],
        'financers.job_title' => [
            'label' => 'Job title',
            'type' => 'relation_field',
            'relation_type' => 'belongsToMany', // belongsTo, hasMany, belongsToMany, hasOne
            'relation_name' => 'financers', // Name of the relation in the User model
            'related_field' => 'job_title_id', // Field to filter in the related table (job_titles.id)
            'related_display_field' => 'name', // Field to display (job_titles.name)
            'operators' => ['equals', 'not_equals'],
        ],
        'financers.job_level' => [
            'label' => 'Job level',
            'type' => 'relation_field',
            'relation_type' => 'belongsToMany', // belongsTo, hasMany, belongsToMany, hasOne
            'relation_name' => 'financers', // Name of the relation in the User model
            'related_field' => 'job_level_id', // Field to filter in the related table (job_levels.id)
            'related_display_field' => 'name', // Field to display (job_levels.name)
            'operators' => ['equals', 'not_equals'],
        ],
        'financers.languages' => [
            'label' => 'Languages',
            'type' => 'relation_field',
            'relation_type' => 'belongsToMany', // belongsTo, hasMany, belongsToMany, hasOne
            'relation_name' => 'financers', // Name of the relation in the User model
            'related_field' => 'language', // Field to filter in the related table (job_levels.id)
            'related_display_field' => 'name', // Field to display (languages.name)
            'operators' => ['in', 'not_in'],
        ],
    ],

    'operators' => [

        // Operators for direct fields
        'equals' => 'Is equal to',
        'not_equals' => 'Is not equal to',
        'more_than' => 'Greater than',
        'less_than' => 'Less than',
        'more_than_or_equal' => 'Greater than or equal to',
        'less_than_or_equal' => 'Less than or equal to',
        'between' => 'Between',
        'in' => 'In list',
        'not_in' => 'Not in list',
        'contains' => 'Contains',
        'starts_with' => 'Starts with',
        'ends_with' => 'Ends with',
        'is_null' => 'Is empty',
        'is_not_null' => 'Is not empty',
        'before' => 'Before',
        'after' => 'After',

        // Operators for relations
        'has' => 'Has at least one',
        'has_not' => 'Does not have',
        'count_equals' => 'Count equals to',
        'count_more_than' => 'Count greater than',
        'count_less_than' => 'Count less than',
        'sum_more_than' => 'Sum greater than',
        'sum_less_than' => 'Sum less than',
        'avg_more_than' => 'Average greater than',
        'avg_less_than' => 'Average less than',
    ],
];
