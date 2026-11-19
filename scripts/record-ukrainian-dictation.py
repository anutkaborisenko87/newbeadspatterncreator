"""Generate the Ukrainian dictation clip bank with Piper 1.8 and the uk_UA-ukrainian_tts-medium voice.

Pass the path to the ONNX model. Keep its matching .onnx.json config alongside it:
https://huggingface.co/rhasspy/piper-voices/tree/371fc4e16222b9560bff27bf2acd5c5b2b82a05b/uk/uk_UA/ukrainian_tts/medium
"""

import json
import sys
from pathlib import Path

import numpy as np
from piper import PiperVoice, SynthesisConfig


words = {
    "row": "ряд", "color": "колір", "quantity": "кількість",
    "1": "один", "2": "два", "one_feminine": "одна", "two_feminine": "дві",
    "4": "чотири", "5": "пʼять", "6": "шість",
    "7": "сім", "8": "вісім", "9": "девʼять", "10": "десять",
    "11": "одинадцять", "12": "дванадцять", "13": "тринадцять",
    "14": "чотирнадцять", "15": "пʼятнадцять", "16": "шістнадцять",
    "17": "сімнадцять", "18": "вісімнадцять", "19": "девʼятнадцять",
    "20": "двадцять", "30": "тридцять", "40": "сорок", "50": "пʼятдесят",
    "60": "шістдесят", "70": "сімдесят", "80": "вісімдесят", "90": "девʼяносто",
    "100": "сто", "200": "двісті", "300": "триста", "400": "чотириста",
    "500": "пʼятсот", "600": "шістсот", "700": "сімсот", "800": "вісімсот",
    "900": "девʼятсот", "thousand_one": "тисяча", "thousand_few": "тисячі",
    "thousand_many": "тисяч", "million_one": "мільйон", "million_few": "мільйони",
    "million_many": "мільйонів", "billion_one": "мільярд",
    "billion_few": "мільярди", "billion_many": "мільярдів",
}

voice = PiperVoice.load(sys.argv[1])
rate = voice.config.sample_rate
apostrophe = next(mark for mark in ("ʼ", "'", "’", "`") if mark in voice.config.phoneme_id_map)
settings = SynthesisConfig(length_scale=1.12, noise_scale=0.4, noise_w_scale=0.5)
destination = Path(__file__).resolve().parents[1] / "resources/audio/bead-dictation/uk"
destination.mkdir(parents=True, exist_ok=True)
bank = bytearray()
clips = {}

for key, word in words.items():
    samples = np.concatenate([
        chunk.audio_int16_array for chunk in voice.synthesize(word.replace("ʼ", apostrophe), syn_config=settings)
    ])
    audible = np.flatnonzero(np.abs(samples.astype(np.int32)) > 96)
    if not audible.size:
        raise ValueError(f"Silent clip: {key}")
    padding = rate // 50
    clip = samples[max(0, audible[0] - padding):audible[-1] + padding + 1].astype("<i2").tobytes()
    clips[key] = [len(bank), len(clip)]
    bank.extend(clip)

(destination / "clips.pcm").write_bytes(bank)
(destination / "clips.json").write_text(json.dumps({"sampleRate": rate, "clips": clips}, indent=2) + "\n", encoding="utf-8")
