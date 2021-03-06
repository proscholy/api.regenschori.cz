const express = require('express');
const app = express();
const port = 3000;

app.get('/match-songs', async (req, res) => {
  try {
    await matchSongsToLiturgicalReadings();
    res.send('Matched');
  }
  catch (e) {
    res.status(400).send({message: e.message});
  }
});

app.get('/get-songs', async(req, res) => {
  const booleanGET = param => (param === undefined || param.toLowerCase() === 'false' ? false : true);

  try {
    const ids = await getSongsIdsMatchingToReference(req.query.reference_str, booleanGET(req.query.is_osis));
    res.setHeader('Content-Type', 'application/json');
    res.send(JSON.stringify(ids));
  } catch (e) {
    res.status(400).send({message: e.message});
  }
});


app.listen(port, () => {
  console.log(`Example app listening at http://localhost:${port}`)
});


// the very logic
require('dotenv').config()
var mysql = require('mysql2/promise');

const BibleReference = require('bible-reference/bible_reference');


function getMatchings(refs, reference) {
  return refs.filter(r => r.ref.intersectsWith(reference));
}

function getLoadedSongReferences(songs) {
  return songs.filter(s => s.bible_refs_osis).flatMap(s => s.bible_refs_osis.split(',').map(ref_str => ({
    id: s.id,
    ref: BibleReference.fromOsis(ref_str)
  })));
}

function getLoadedLiturgicalYearReadingReferences(liturgical_year_readings) {
  for (let r of liturgical_year_readings) {
    mergeReadings(r);
  }

  return liturgical_year_readings.flatMap(litYearReferenceConverter);
}

function mergeReadings(lit_ref_db) 
{
  if (lit_ref_db.reference_1 !== null && lit_ref_db.reference_1 == lit_ref_db.reference_2) {
    if (lit_ref_db.reference_1 == lit_ref_db.reference_3) {
      lit_ref_db.reference_2 = null;
      lit_ref_db.reference_3 = null;
    } else if (lit_ref_db.reference_3 == null) {
      lit_ref_db.reference_2 = null;
    } 
  }
}

const litYearReferenceConverter = lit_year => {
  const common = {
    id: lit_year.id,
    date: lit_year.date,
    type: lit_year.reference_type
  };

  // just a buch of references in reference_others
  if (!lit_year.reference_1) {
    if (lit_year.reference_others) {
      return lit_year.reference_others.split(',').map(ref_str => ({
        ...common,
        ref: BibleReference.fromOsis(ref_str),
        cycle: '-'
      }));
    } else {
      return [];
    }
  }

  // sunday - 3 cycles
  if (lit_year.reference_2) {
    let arr = [
      {
        ...common,
        ref: BibleReference.fromOsis(lit_year.reference_1),
        cycle: lit_year.reference_3 ? 'A' : '1'
      },
      {
        ...common,
        ref: BibleReference.fromOsis(lit_year.reference_2),
        cycle: lit_year.reference_3 ? 'B' : '2'
      },
    ];

    if (lit_year.reference_3) {
      arr.push({
        ...common,
        ref: BibleReference.fromOsis(lit_year.reference_3),
        cycle: 'C'
      })
    }
    return arr;
  }

  // only reference_1

  return [{
    ...common,
    ref: BibleReference.fromOsis(lit_year.reference_1),
    cycle: '-'
  }]
}

function getDbConnection() {
  return mysql.createConnection({
    host     : process.env.DB_HOST,
    user     : process.env.DB_USERNAME,
    password : process.env.DB_PASSWORD,
    database : process.env.DB_DATABASE,
    port: process.env.DB_PORT
  });
}

async function getSongsIdsMatchingToReference(reference_str, is_osis = false) {
    const connection = await getDbConnection();
    
    let reference;

    if (is_osis) {
      reference = BibleReference.fromOsis(reference_str);
    } else {
      reference = BibleReference.fromEuropean(reference_str);
    }

    console.log(is_osis);

    // extract all song_lyrics 
    let [rows] = await connection.execute('SELECT id, bible_refs_osis from song_lyrics');
    const loaded_song_references = getLoadedSongReferences(rows);

    const matchings = getMatchings(loaded_song_references, reference);
    return matchings.map(m => m.id);
}


async function matchSongsToLiturgicalReadings() {
  const connection = await getDbConnection();

  var db_res = {
    song_lyrics: [],
    liturgical_year_readings: []
  }

  // extract all song_lyrics 
  let [rows] = await connection.execute('SELECT id, bible_refs_osis from song_lyrics');
  db_res.song_lyrics = rows;

  // extract liturgical references
  [rows] = await connection.execute('SELECT id, DATE_FORMAT(date,"%Y-%m-%d") as date, reference_type, reference_1, reference_2, reference_3, references_others from liturgical_year_readings');
  db_res.liturgical_year_readings = rows;


  const loaded_song_references = getLoadedSongReferences(db_res.song_lyrics);
  const loaded_liturgical_year_readings = getLoadedLiturgicalYearReadingReferences(db_res.liturgical_year_readings);

  await connection.execute('TRUNCATE liturgical_references');

  for (const song_ref of loaded_song_references) {
    const lit_matchings = getMatchings(loaded_liturgical_year_readings, song_ref.ref);
    
    for (const lit_matching of lit_matchings){
      // console.log(lit_matching);
      await connection.execute(`INSERT into liturgical_references (song_lyric_id, type, cycle, reading, date) values (
        ${song_ref.id},
        "${lit_matching.type}",
        "${lit_matching.cycle}",
        "${lit_matching.ref.toString()}",
        "${lit_matching.date}"
      )`);
    }
  }

  connection.end(function(err) {
    if (err) throw error;
  });
}

// doJob();