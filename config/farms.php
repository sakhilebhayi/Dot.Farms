<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Removed-record retention
    |--------------------------------------------------------------------------
    | Deleting a farm, field, crop, crop cycle, planting or harvest record is a
    | soft delete: it can be restored from the Removed screen. The daily
    | `farms:purge-removed` command deletes records for good once they have been
    | removed for longer than this many days. Must be at least 1.
    */

    'removed_retention_days' => (int) env('REMOVED_RETENTION_DAYS', 90),

];
