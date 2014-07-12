# -*- mode: ruby -*-
# vi: set ft=ruby :

# Vagrantfile API/syntax version. Don't touch unless you know what you're doing!
VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|
  # All Vagrant configuration is done here. The most common configuration
  # options are documented and commented below. For a complete reference,
  # please see the online documentation at vagrantup.com.

  # Every Vagrant virtual environment requires a box to build off of.
  #config.vm.box = "precise32"
  config.vm.box = "ORGapi2"

  # The url from where the 'config.vm.box' box will be fetched if it
  # doesn't already exist on the user's system.
  config.vm.box_url = "http://dretzq.org.uk/vagrant/ORGapi2.box"

  # Create a forwarded port mapping which allows access to a specific port
  # within the machine from a port on the host machine. In the example below,
  # accessing "localhost:8080" will access port 80 on the guest machine.
  config.vm.network :forwarded_port, guest: 80, host: 8080
  config.vm.network :forwarded_port, guest: 5672, host: 5672

  #config.vm.provision "ansible" do |ansible|
  #  ansible.inventory_file = 'ansible/vagrant_ansible_inventory_default'
  #  ansible.playbook = 'ansible/ansible.yml'
  #end

  # shell script for updating the VM from a fresh checkout
  config.vm.provision "shell", path: "Vagrant-provision.sh"

end
