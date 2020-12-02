var t = this;

// Process all the events listed, store information and schedule notifications.
var urls = {};
var promises = [];
t.INIT_OTHERDATA.events.forEach(function(event) {
    promises.push(t.CoreLocalNotificationsProvider.getUniqueNotificationId(event.id, 'local_ounotifications',
            t.CoreSitesProvider.getCurrentSiteId()).then(function(uniqueId) {
        var eventId = 'notification' + uniqueId;
        urls[eventId] = event.url;

        // Do not schedule past events.
        var timestamp = event.timestamp * 1000;
        if (timestamp < Date.now()) {
            return;
        }

        var notification = {
            id: uniqueId,
            title: event.title,
            text: event.text,
            launch: true,
            trigger: {
                at: new Date(timestamp)
            },
            data: {
                eventId: eventId
            }
        };

        t.CoreLocalNotificationsProvider.schedule(notification, 'local_ounotifications',
                t.CoreSitesProvider.getCurrentSiteId(), true);
    }));
});

// Function to go to the event URL.
function launchEvent(eventId) {
    if (urls[eventId]) {
        t.CoreContentLinksHelperProvider.handleLink(urls[eventId]);
    }
}

// After we've processed all the events, check if the app was launched by an event,
// because we don't get a callback for that.
Promise.all(promises).then(function() {
    if (window.cordova !== undefined && window.cordova.plugins !== undefined &&
            window.cordova.plugins.notification !== undefined &&
            window.cordova.plugins.notification.local !== undefined &&
            window.cordova.plugins.notification.local.launchDetails !== undefined) {
        var eventId = 'notification' + window.cordova.plugins.notification.local.launchDetails.id;
        launchEvent(eventId);
    }
});

// Listen for clicks on the events.
t.CoreLocalNotificationsProvider.registerClick('local_ounotifications', function(data) {
    if (data.eventId) {
        launchEvent(data.eventId);
    }
});
