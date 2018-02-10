const fs        = require('fs')
let configfile  = fs.readFileSync('/data/.config.js');
let config      = JSON.parse(configfile)

console.log(config)

process.exit(0)

const Discord = require('discord.js');
const client = new Discord.Client();

client.on('ready', () => {
  console.log(`Logged in as ${client.user.tag}!`);
});

client.on('message', msg => {
  if (msg.content === 'ping') {
    msg.reply('private pong')
    msg.channel.send('public pong')
  } else {
    console.log(msg.content)
    console.log(msg.author.username) // id // bot == false
    console.log(msg.mentions.users)
    var text = msg.content.replace(/<@([0-9]+)>/g, function (match, contents, offset, input_string) {
        console.log(match)
    })
  }
});

client.login('');

