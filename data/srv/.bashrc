# .bashrc
cd /srv/$WP_ENV

if [ -f /srv/.aliases ]; then
        . /srv/.aliases
fi
