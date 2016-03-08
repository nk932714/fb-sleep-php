# fb-sleep-php
A pretty basic facebook friend activity tracker in PHP

this was inspired by the medium post "[How you can use facebook to track your friends sleeping habits](https://medium.com/@sqrendk/how-you-can-use-facebook-to-track-your-friends-sleeping-habits-505ace7fffb6#.vdumf15vq)"

![image](https://cloud.githubusercontent.com/assets/1279725/13605376/92152682-e547-11e5-9345-ee5fd0703bf7.png)

## Instructions
- get [facebook.php](https://github.com/facebookarchive/facebook-php-sdk/blob/8e7e7951e99d86b68ce1135537d559663d759af0/src/facebook.php) and save it.
- get [base_facebook.php](https://github.com/facebookarchive/facebook-php-sdk/blob/8e7e7951e99d86b68ce1135537d559663d759af0/src/base_facebook.php) and save it.
- make sure your directory is writable, so the sqlite database can be created automatically
- look inside `index.php` and fill in the required configuration details according to the comments: App ID/Secret, Facebook profile URL, Facebook profile ID and XS cookie value (use the developer tools on facebook.com to find this).
- create a cron job or some other way to have `index.php?update` called in regular intervals. I suggest 10-20 minutes.
- let it run for a few days and have a look at the stats.

**please note:** over time the database may grow relatively large, depending on how many friends you're tracking. mine grows about 50-100kb a day. there is currently no way to limit this and you'll have to devise your own, or simply delete the database every month or so. or stop the cron job.
