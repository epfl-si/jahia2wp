# HOWTO update the WP files from container-wp-volumes

The theme and our supported plugins are located in tow places:

1. historically, in [container-wp-volumes](https://github.com/epfl-si/container-wp-volumes). This repository is used by [jahiap](https://github.com/epfl-si/jahiap), the historical repository of the project. That is the place where Aline pushes her updates
2. in this repository/[data](https://github.com/epfl-si/jahia2wp/tree/master/data). This is  the source of the code for the current phase of the project

As for now, the current repository must be manually updated.

## Pre-requisites

You have cloned the two repositories with your favorite method, i.e SSH or HTTPS.

We will make the assumptions that you have your repos in the following paths, in the `master` branch:

    container-wp-volumes (master) $

    jahia2wp (master) $

## Procedure

1. Update your repos (merge if necessary)

        container-wp-volumes (master) $ git pull

        jahia2wp (master) $ git pull
        jahia2wp (master) $ git checkout fix-update-wp-content-from-container-wp-volumes
        jahia2wp (fix-update-wp-content-from-container-wp-volumes)$ git pull
        jahia2wp (fix-update-wp-content-from-container-wp-volumes)$ git merge origin master

2. RSync

        jahia2wp (fix-...-wp-volumes) $ rsync -av ../container-wp-volumes/wp-content/ data/wp/wp-content/

3. Update changelog if necessary

4. Review, commit and push

        jahia2wp (fix-...-wp-volumes) $ git status
        jahia2wp (fix-...-wp-volumes) $ git commit -am "updated wp-content with content of last sprint"
        jahia2wp (fix-...-wp-volumes) $ git push

5. Create PR on [github](https://github.com/epfl-si/jahia2wp/branches). You probably can use your CHANGELOG description as the description of your PR
