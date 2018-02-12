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
    var fromUid = msg.author.id
    var fromUsername = msg.author.username

    var toUid = ''
    var toUsername = ''
    var tipbotMentioned = false
    var uid = ''
    var user = null
    var toUserObject = null
    var username = ''

    var text = msg.content.replace(/<@\!*([0-9]+)>/g, function (match, contents, offset, input_string) {
      uid = match.replace(/^<@\!*/, '').replace(/>$/, '')
      user = msg.mentions.users.get(uid)

      if (user !== null && typeof user !== 'undefined') {
        if (Object.keys(user).indexOf('username') > -1) {
          username = user.username
        }

        if (username.toLowerCase() === 'xrptipbot') {
          if (uid !== fromUid) {
            // Doesn't count if Tipbot is the sender.
            tipbotMentioned = true
          }
        }

        if (uid !== fromUid && username !== 'XRPTipBot') {
          if (toUid === '') {
            toUid = uid
            toUsername = username
            toUserObject = user
          }
        }
      }
    })

    console.log('')
    console.log('--- << DISCORD >> ---')
    console.log(msg.content)
    console.log('')

    if (tipbotMentioned) {
      console.log('Tipping amount: ', tipAmount)
      console.log('')
      console.log('From:   ' + fromUid + ' = ' + fromUsername)
      console.log('    >   ' + uid + ' = ' + username)
      console.log('  To:   ' + toUid + ' = ' + toUsername)

      var replyToMsg = function (text) {
        var msgContent = msg.content
        var channel = msg.channel
        var msgGuidlName =
        msg.delete().then(function(){
          console.log('1. Deleted msg')
          console.log('2. Send merged msg to channel')
          channel.send('<@' + fromUid + '> ' + text + ' (' + msgContent + ')')
          console.log('3. Confirm to recipient using PB to [' + toUsername + ']')
          if (toUserObject !== null && text.match(/:tada:/)) {
            toUserObject.send('Big thanks to <@' + fromUid + '>! ' + text + ' - More info on https://www.xrptipbot.com' +
                              "\n" +
                              ' -- <@' + fromUid + '> in ' + channel.guild.name + ' (#' + channel.name + ') : ' + msgContent)
          }
        }).catch(function (e) {
          console.log('1. Cannot delete, just reply')
          msg.reply(text)
        })
      }

      if (isNaN(tipAmount) || tipAmount > 5 || tipAmount === 0 || tipAmount < 0) {
        if (tipAmount > 5) {
          replyToMsg('There\'s a tip maximum of 5 XRP')
        } else {
          replyToMsg('Invalid tip amount: "' + tip[1] + '"')
        }
      } else if (toUid === '') {
        replyToMsg('Cannot detect who you wanted to tip, please mention the user to be tipped :innocent:')
      } else if (fromUid === toUid) {
        replyToMsg('You cannot tip yourself')
      } else {
        var safeGuild = msg.channel.guild.name.replace(/[^a-zA-Z0-9 _\-\.\(\),]/g, '')
        safeGuild += '#'
        safeGuild += msg.channel.name.replace(/[^a-zA-Z0-9 _\-\.\(\),]/g, '')

        let cmd = spawn('/usr/bin/php', [ '/data/cli/discord/process.php', fromUid, toUid, tipAmount, toUsername, safeGuild ]) // , ['-lh', '/tmp']
        cmd.stdout.on('data', function (data) {
          replyToMsg(data.toString().trim())
        });
      }
    }
  }
});

client.login(config.discord.secret);

