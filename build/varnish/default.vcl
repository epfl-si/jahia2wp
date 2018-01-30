vcl 4.0;

backend default {
    .host = "localhost";
    .port = "8080";
}


# Set the allowed IPs of purge requests
acl purge {
    "localhost";
    "127.0.0.1";
}


sub vcl_recv {

    # Handle purge requests
    if (req.method == "PURGE") {
        if (client.ip !~ purge) {
            return (synth(405));
        }

        if (req.http.X-Purge-Method == "regex") {
            ban("req.url ~ " + req.url + " && req.http.host ~ " + req.http.host);
            return (synth(200, "Banned."));
        } else {
            return (purge);
        }
    }

    # Do not cache rss feed
    if (req.url ~ "/feed(/)?") {
	    return ( pass );
    }

    # Do not cache cron
    if (req.url ~ "wp-cron\.php.*") {
        return ( pass );
    }

    # Do not cache search result
    if (req.url ~ "/\?s\=") {
	    return ( pass );
    }

    # Exclude admin and login urls
    if (req.url ~ "wp-admin|wp-login") {
        return (pass);
    }

    # Ignore some wp cookies
    set req.http.cookie = regsuball(req.http.cookie, "wp-settings-\d+=[^;]+(; )?", "");
    set req.http.cookie = regsuball(req.http.cookie, "wp-settings-time-\d+=[^;]+(; )?", "");
    set req.http.cookie = regsuball(req.http.cookie, "wordpress_test_cookie=[^;]+(; )?", "");
    if (req.http.cookie == "") {
        unset req.http.cookie;
    }

}


# Extend cache
sub vcl_backend_response {
    if (beresp.ttl == 120s) {
        set beresp.ttl = 1h;
    }
}
