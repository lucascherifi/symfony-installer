Build
=====

    box build -v && scp symfony-vagrant.phar dedie:~/www/kasifi.com/sfvg/installer && rm -rf symfony-vagrant.phar

Usage
=====

    curl -LSs http://kasifi.com/sfvg/installer -o /usr/local/bin/symfony-vagrant
    sudo chmod a+x /usr/local/bin/symfony-vagrant
