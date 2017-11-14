# HOWTO create a cronjob on openshift infra

Cette page propose les recettes pour créer et gérer des cronjobs.
Vous pouvez vous référer à [la documentation officielle](https://docs.openshift.org/latest/dev_guide/cron_jobs.html) pour plus de détails.

## Pre-requisite : login

    $ oc login --token="$(cat /var/run/secrets/kubernetes.io/serviceaccount/token)" --insecure-skip-tls-verify=true https://os-exopge.epfl.ch:443 

## Creating a job

Vous devez avoir préparé une descrition de votre cronjob, c'est à dire un fichier YAML.

Ils sont définis dans le repo à `etc/cronjobs/*.yaml`. Le premier exemple est `build-dev-inventory.yml`

    $ oc create -f test-cronjob-definition.yaml

## Listing jobs

    $ oc get cronjobs

## Listing logs for a job

our accéder aux logs, vous devez d'abord identifier sur quel pod votre cronjob tourne, Ensuite, utilisez `oc logs <pod>`:

    $ oc get pods
    NAME                                       READY     STATUS             RESTARTS   AGE
    test-cronjob-definition-1510669500-dkvh1   0/1       CrashLoopBackOff   28         2h
    ...
    $ oc logs test-cronjob-definition-1510669500-dkvh1

## Deleting jobs

    oc delete cronjob test-cronjob-definition
