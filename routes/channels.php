<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| The default channel Laravel's broadcast notification channel uses
| (Notifiable::receivesBroadcastNotificationsOn()) -- authorizes a user to
| receive only their own broadcast notifications. HarvestRecordedNotification
| broadcasts here once it adds the 'broadcast' channel to via().
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return is_string($id) && ctype_digit($id) && $user->id === (int) $id;
});
