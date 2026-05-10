import crypto from 'node:crypto';

const ENCODE_ALPHABET = 'swxdyktzhgjfblrpmcqvn';

export const encodeId = (value: string, seed = 0): string => {
  const hashHex = crypto.createHash('sha1').update(value).digest('hex').slice(0, 8);
  const hash = Number.parseInt(hashHex, 16) >>> 0;

  let number = (BigInt(hash) << 16n) | BigInt(seed & 0xffff);
  const base = BigInt(ENCODE_ALPHABET.length);

  if (number === 0n) {
    return ENCODE_ALPHABET[0];
  }

  let encoded = '';

  while (number > 0n) {
    encoded = ENCODE_ALPHABET[Number(number % base)] + encoded;
    number /= base;
  }

  return encoded;
};

export const generateProjectId = (url: string): string => {
  const seed = crypto.randomInt(0, 0x10000);
  return encodeId(url, seed);
};
