
Installation instructions
=========================


Requirements

Server

 * A server running Debian 7
 * 1GB+ RAM
 * 4GB+ Disk space
 * Root access (either by connecting directly with SSH, or by connecting as an
   unprivileged user and using sudo.

Probe

 * Raspberry Pi v2
 * 2GB+ SD Card
 * Raspbian Wheezy

A set of ansible playbooks has been provided to set up the server and initial
probe image.  These playbooks will set up the server with a self-signed
certificate for HTTPS and for an OpenVPN management network.  

Server installation
-------------------

This guide assumes basic server administration proficiency.  You should be
comfortable with the command line and SSH.

Instructions for creating the SSL certificates for the system are included in
ansible/files/ssl/README.txt.

Installing ansible

Run: 

    pip install ansible
    apt-get install sshpass

Creating the inventory

In the ansible/ directory, rename/copy the inventory.example file to
inventory, and amend the file so that your server name is correct.

Setting up deployment variables

The system requires various passwords and user accounts to be created.  Edit
the group_vars/deployment.yml file to set hostnames, usernames and passwords
to suit your deployment.

Creating SSL keys

Read the ansible/files/ssl/README.txt file, and follow the instructions.

Running the playbooks

Run:

    cd ansible/
    ansible-playbook -i inventory apiservers.yml [-k] [-u username] [-K] [-s]

Additional parameters:

 * -k supply an SSH password for connection
 * -u connect as a less privileged user
 * -s use sudo to gain root privileges
 * -K supply a sudo password to become root

The playbook should then run, installing all of the system software and
setting up the components.


Probe installation
------------------

Download Raspbian 7 from the raspberry pi website:
https://www.raspberrypi.org/downloads/raspbian/

or 

http://sourceforge.net/projects/minibian/

(tested with the 2015-02-18 image)



Follow the instructions to write the image to an SD card, then boot a
raspberry pi.

You may need to resize the partitions on your memory card to expand the
available capacity.

If you are using minibian, SSH onto the raspberry pi and install python:

    ssh root@<raspi ip address>
    apt-get install -y python

Add the raspberry pi's IP address to the ansible inventory file.

Run:

    ansible-playbook -i inventory -k probes.yml 
    <enter default raspbian root password: raspberry>

The probe system will be deployed onto the raspberry pi.  Once it has been
completed, you can shut down the raspberry pi and clone the SD card to provide
a base image for your other probes.

