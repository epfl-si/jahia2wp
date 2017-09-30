# .bashrc
alias gowp="cd /srv/${WP_ENV}/jahia2wp"

gowp

if [ -f /srv/.aliases ]; then
    . /srv/.aliases
fi
