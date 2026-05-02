/** Read all of stdin and return as a string. */
const readStdin = () => new Promise((resolve) => {
  const chunks = [];
  process.stdin.on('data', (chunk) => chunks.push(chunk));
  process.stdin.on('end', () => resolve(Buffer.concat(chunks).toString()));
});

module.exports = {
  readStdin,
};
