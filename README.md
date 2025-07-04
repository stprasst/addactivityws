# Moodle Add Activity Web Service
External Webservice Moodle for add activity

## URL
curl -X POST "http://<Moodle-site>>/webservice/rest/server.php" \
 -d "wstoken=TOKEN" \
 -d "wsfunction=local_addactivityws_add_activity" \
 -d "moodlewsrestformat=json" \
 -d "courseid=2" \
 -d "sectionnum=1" \
 -d "activitytype=url" \
 -d "name=My External URL" \
 -d "intro=intro" \
 -d "url=http://pastihebat.com" \
 -d "completion=0"

## ASSIGNMENT
curl -X POST "http://<Moodle-site>>/webservice/rest/server.php" \
 -d "wstoken=TOKEN" \
 -d "wsfunction=local_addactivityws_add_activity" \
 -d "moodlewsrestformat=json" \
 -d "courseid=2" \
 -d "sectionnum=1" \
 -d "activitytype=assign" \
 -d "name=My Assignment" \
 -d "intro=This is the assignment description." \
 -d "allowfrom=1688304000" \
 -d "duedate=1688899200" \
 -d "cutoffdate=1688985600" \
 -d "completion=1"

