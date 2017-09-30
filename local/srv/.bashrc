# .bashrc
alias gowp="cd /srv/${WP_ENV}"

gowp

if [ -f /srv/.aliases ]; then
    . /srv/.aliases
fi
