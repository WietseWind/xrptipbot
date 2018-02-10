const fs = require('fs')
let configfile = fs.readFileSync('/data/.config.js');
let config = JSON.parse(configfile)

const Discord = require('discord.js')
const client = new Discord.Client()

const spawn = require('child_process').spawn

client.on('ready', () => {
  console.log(`Logged in as ${client.user.tag}!`);
});

console.log('Here we go...')

client.on('message', msg => {
  var tip = msg.content.match(/\+[ ]*([0-9,\.]+)/)
  if (tip) {
    var tipAmount = parseFloat(parseFloat(tip[1].replace(/,/g, '.')).toFixed(6))
    console.log('')
    console.log('------')
    console.log('Tipping amount: ', tipAmount)
    console.log('')
    console.log(msg.content)

    var fromUid = msg.author.id
    var fromUsername = msg.author.username

    console.log('From: ' + fromUid + ' = ' + fromUsername)

    var toUid = ''
    var toUsername = ''

    var text = msg.content.replace(/<@([0-9]+)>/g, function (match, contents, offset, input_string) {
      var uid = match.replace(/^<@/, '').replace(/>$/, '')
      var user = msg.mentions.users.get(uid)

      var username = ''
      if (Object.keys(user).indexOf('username') > -1) {
        username = user.username
      }

      if (uid !== fromUid && username !== 'XRPTipBot') {
        console.log('    > ' + uid + ' = ' + username)
        if (toUid === '') {
          toUid = uid
          toUsername = username
        }
      }
    })

    if (isNaN(tipAmount) || tipAmount > 5 || tipAmount === 0 || tipAmount < 0) {
      if (tipAmount > 5) {
        msg.reply('There\'s a tip maximum of 5 XRP')
      } else {
        msg.reply('Invalid tip amount: "' + tip[1] + '"')
      }
    } else if (toUid === '') {
      msg.reply('Cannot detect who you wanted to tip, please mention the user to be tipped :innocent:')
    } else if (fromUid === toUid) {
      msg.reply('You cannot tip yourself')
    } else {
      let cmd = spawn('/usr/bin/php', [ '/data/cli/discord/process.php', fromUid, toUid, tipAmount ]) // , ['-lh', '/tmp']
      cmd.stdout.on('data', function (data) {
        // msg.reply('Tipped **' + tipAmount + ' XRP** to <@' + toUid + '> :tada:')
        msg.reply(data.toString().trim())
        // msg.channel.send()
      });
    }
  }
});

client.login(config.discord.secret);

