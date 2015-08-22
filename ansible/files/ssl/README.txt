
Creating openvpn keys

Install openvpn-easyrsa

Copy /usr/share/easyrsa to a directory under your $HOME.

Edit the file named "vars", filling in the various organisation details.

Run:

source vars
./clean-all

Run:

./build-ca

(accept defaults, but enter CaCert in the "Name" field.

./build-key-server <servername>

(accept defaults, enter ServerCert in the "Name" field

./build-key client

(accept defaults, choose a unique "Name" for each client certificate you generate)

./build-dh

Accept the defaults for each of the prompts, but pick distinct certificate names.
Save the certificates at each step.

Then, copy the following files from easyrsa/keys into this (ansible/files/ssl) directory:
<servername>.crt
<servername>.key
ca.crt
ca.key
dh2048.pem
client.crt
client.key

