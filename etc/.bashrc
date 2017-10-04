# .bashrc

if [ -f /srv/.aliases ]; then
    . /srv/.aliases
fi

export LOGGING_FILE=/srv/${WP_ENV}/logs/jahia2wp.log

cd /srv/${WP_ENV}
