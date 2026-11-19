#!/usr/bin/env python3
"""Baixa o relevo da região (modelo digital de elevação) e grava em um arquivo PMTiles próprio.

Fonte: Mapzen Terrain Tiles, do programa AWS Open Data (codificação "terrarium").
Na região, os dados derivam do SRTM (NASA/USGS), com cerca de 30 m de resolução:
por isso o zoom 12 já é a resolução nativa e não há ganho em baixar mais detalhe.

Uso:  python3 scripts/geo/extrair-relevo.py
Exige: pip install pmtiles
"""
import json
import math
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

from pmtiles.tile import Compression, TileType, zxy_to_tileid
from pmtiles.writer import Writer

RAIZ = Path(__file__).resolve().parents[2]
DESTINO = RAIZ / "public" / "mapa" / "relevo.pmtiles"
BASE = "https://s3.amazonaws.com/elevation-tiles-prod/terrarium/{z}/{x}/{y}.png"
# Região ampla até o zoom 10; entorno do município até o zoom 12 (resolução nativa dos dados).
REGIAO = (-52.05, -27.78, -51.20, -27.08)
BBOX = (-51.80, -27.57, -51.46, -27.27)
ZOOM_MIN, ZOOM_REGIAO, ZOOM_MAX = 7, 10, 12


def tile_xy(lon, lat, z):
    n = 2 ** z
    x = int((lon + 180.0) / 360.0 * n)
    y = int((1.0 - math.asinh(math.tan(math.radians(lat))) / math.pi) / 2.0 * n)
    return x, y


def baixar(zxy):
    z, x, y = zxy
    req = urllib.request.Request(BASE.format(z=z, x=x, y=y), headers={
        "User-Agent": "mapa-turistico-capinzal/1.0 (extracao pontual do relevo)",
    })
    for tentativa in range(4):
        try:
            with urllib.request.urlopen(req, timeout=60) as resp:
                return zxy, resp.read()
        except Exception:  # noqa: BLE001
            if tentativa == 3:
                raise


def main():
    pedidos = []
    for z in range(ZOOM_MIN, ZOOM_MAX + 1):
        oeste, sul, leste, norte = REGIAO if z <= ZOOM_REGIAO else BBOX
        x0, y0 = tile_xy(oeste, norte, z)
        x1, y1 = tile_xy(leste, sul, z)
        pedidos += [(z, x, y) for x in range(x0, x1 + 1) for y in range(y0, y1 + 1)]
    oeste, sul, leste, norte = REGIAO
    with ThreadPoolExecutor(max_workers=6) as pool:
        blocos = sorted(pool.map(baixar, pedidos), key=lambda item: zxy_to_tileid(*item[0]))

    DESTINO.parent.mkdir(parents=True, exist_ok=True)
    with open(DESTINO, "wb") as f:
        escritor = Writer(f)
        for (z, x, y), dados in blocos:
            assert dados[:8] == b"\x89PNG\r\n\x1a\n", f"bloco {z}/{x}/{y} não é PNG"
            escritor.write_tile(zxy_to_tileid(z, x, y), dados)
        escritor.finalize(
            {
                "tile_type": TileType.PNG,
                "tile_compression": Compression.NONE,
                "min_zoom": ZOOM_MIN,
                "max_zoom": ZOOM_MAX,
                "min_lon_e7": int(oeste * 1e7),
                "min_lat_e7": int(sul * 1e7),
                "max_lon_e7": int(leste * 1e7),
                "max_lat_e7": int(norte * 1e7),
                "center_zoom": 12,
                "center_lon_e7": int(-51.6110 * 1e7),
                "center_lat_e7": int(-27.3441 * 1e7),
            },
            {
                "name": "Relevo da região de Capinzal",
                "encoding": "terrarium",
                "attribution": "Mapzen Terrain Tiles (AWS Open Data); SRTM: NASA/USGS",
            },
        )
    tamanho = DESTINO.stat().st_size
    print(f"{len(blocos)} blocos em {DESTINO.relative_to(RAIZ)} ({tamanho / 1e6:.2f} MB)")
    (DESTINO.parent / "relevo.pmtiles.json").write_text(json.dumps({
        "origem": "https://registry.opendata.aws/terrain-tiles/",
        "codificacao": "terrarium",
        "regiao": {"bbox": REGIAO, "zoom": [ZOOM_MIN, ZOOM_REGIAO]},
        "detalhe": {"bbox": BBOX, "zoom": [ZOOM_REGIAO + 1, ZOOM_MAX]},
        "blocos": len(blocos),
        "bytes": tamanho,
        "creditos": "Mapzen Terrain Tiles (AWS Open Data). Na região, os dados derivam do SRTM (NASA/USGS).",
    }, ensure_ascii=False, indent=2) + "\n")


if __name__ == "__main__":
    main()
