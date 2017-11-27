# HOWTO create a cronjob on openshift infrastructure

You will find on this page a few recipes to create and manage cronjobs.
Please refer to the [official documentation](https://docs.openshift.org/latest/dev_guide/cron_jobs.html) for more details.

## Pre-requisite : login

    $ oc login --token="$(cat /var/run/secrets/kubernetes.io/serviceaccount/token)" --insecure-skip-tls-verify=true https://os-exopge.epfl.ch:443 

## Creating a job

You should have prepared a job description in YAML format. You will find examples in the directory `etc/cronjobs`. For instance [etc/jobs/build-dev-inventory.yaml](../../etc/jobs/build-dev-inventory.yaml)

    $ oc create -f test-cronjob-definition.yaml

## Listing jobs

    $ oc get cronjobs

## Listing logs for a job

In order to access the logs, you first need to know which pod runs your job. Once you know it, use the command `oc logs <pod>`:

    $ oc get pods
    NAME                                       READY     STATUS             RESTARTS   AGE
    test-cronjob-definition-1510669500-dkvh1   0/1       CrashLoopBackOff   28         2h
    ...
    $ oc logs test-cronjob-definition-1510669500-dkvh1

## Deleting jobs

    oc delete cronjob test-cronjob-definition
