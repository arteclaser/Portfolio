#!/usr/bin/env python3
"""Extrai o mapa-base vetorial da região de Capinzal para um arquivo PMTiles próprio.

Fonte: compilação diária do Protomaps Basemaps (dados © colaboradores do OpenStreetMap, ODbL),
lida por requisições parciais (HTTP Range). O resultado fica no próprio site, sem depender de
serviços de mapas de terceiros.

Uso:  python3 scripts/geo/extrair-mapa-base.py [AAAAMMDD]
Exige: pip install pmtiles
"""
import gzip
import json
import math
import sys
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

from pmtiles.reader import Reader
from pmtiles.tile import Compression, TileType, zxy_to_tileid
from pmtiles.writer import Writer

RAIZ = Path(__file__).resolve().parents[2]
DESTINO = RAIZ / "public" / "mapa" / "capinzal.pmtiles"

# Duas áreas (oeste, sul, leste, norte):
#  - REGIAO: contexto do Vale do Rio do Peixe, só até o zoom 12 (para situar o município e os acessos);
#  - BBOX:   Capinzal, Ouro e vizinhança imediata, com todo o detalhe (até o zoom 15).
REGIAO = (-52.05, -27.78, -51.20, -27.08)
BBOX = (-51.80, -27.57, -51.46, -27.27)
ZOOM_MIN, ZOOM_REGIAO, ZOOM_MAX = 6, 12, 15


def tile_xy(lon, lat, z):
    n = 2 ** z
    x = int((lon + 180.0) / 360.0 * n)
    y = int((1.0 - math.asinh(math.tan(math.radians(lat))) / math.pi) / 2.0 * n)
    return x, y


def main():
    data = sys.argv[1] if len(sys.argv) > 1 else None
    if not data:
        sys.exit("Informe a data da compilação (AAAAMMDD), por exemplo 20261118.")
    url = f"https://build.protomaps.com/{data}.pmtiles"
    cache = {}

    def get_bytes(offset, length):
        chave = (offset, length)
        if chave in cache:
            return cache[chave]
        req = urllib.request.Request(url, headers={
            "Range": f"bytes={offset}-{offset + length - 1}",
            "User-Agent": "mapa-turistico-capinzal/1.0 (extracao pontual do mapa-base)",
        })
        for tentativa in range(4):
            try:
                with urllib.request.urlopen(req, timeout=60) as resp:
                    dados = resp.read()
                break
            except Exception:  # noqa: BLE001 - rede instável: tenta de novo
                if tentativa == 3:
                    raise
        # Só os diretórios (pequenos e repetidos) ficam em memória.
        if length < 200_000 and len(cache) < 4000:
            cache[chave] = dados
        return dados

    leitor = Reader(get_bytes)
    cabecalho = leitor.header()
    metadados = leitor.metadata()
    print("Compilação:", data, "· zoom", cabecalho["min_zoom"], "a", cabecalho["max_zoom"],
          "· compressão dos blocos:", cabecalho["tile_compression"])

    pedidos = []
    for z in range(ZOOM_MIN, min(ZOOM_MAX, cabecalho["max_zoom"]) + 1):
        oeste, sul, leste, norte = REGIAO if z <= ZOOM_REGIAO else BBOX
        x0, y0 = tile_xy(oeste, norte, z)
        x1, y1 = tile_xy(leste, sul, z)
        for x in range(x0, x1 + 1):
            for y in range(y0, y1 + 1):
                pedidos.append((z, x, y))
    oeste, sul, leste, norte = REGIAO
    print(len(pedidos), "blocos a ler")

    def ler(zxy):
        return zxy, leitor.get(*zxy)

    with ThreadPoolExecutor(max_workers=8) as pool:
        blocos = list(pool.map(ler, pedidos))
    blocos = [(zxy, b) for zxy, b in blocos if b]
    blocos.sort(key=lambda item: zxy_to_tileid(*item[0]))

    DESTINO.parent.mkdir(parents=True, exist_ok=True)
    with open(DESTINO, "wb") as f:
        escritor = Writer(f)
        for (z, x, y), dados in blocos:
            escritor.write_tile(zxy_to_tileid(z, x, y), dados)
        escritor.finalize(
            {
                "tile_type": TileType.MVT,
                "tile_compression": cabecalho["tile_compression"],
                "min_zoom": ZOOM_MIN,
                "max_zoom": min(ZOOM_MAX, cabecalho["max_zoom"]),
                "min_lon_e7": int(oeste * 1e7),
                "min_lat_e7": int(sul * 1e7),
                "max_lon_e7": int(leste * 1e7),
                "max_lat_e7": int(norte * 1e7),
                "center_zoom": 13,
                "center_lon_e7": int(-51.6110 * 1e7),
                "center_lat_e7": int(-27.3441 * 1e7),
            },
            {
                **{k: v for k, v in metadados.items() if k in ("vector_layers", "attribution", "version", "type", "name", "description")},
                "origem": url,
                "recorte": {"regiao": REGIAO, "zoom_regiao": [ZOOM_MIN, ZOOM_REGIAO], "bbox": BBOX, "zoom_detalhe": [ZOOM_REGIAO + 1, ZOOM_MAX]},
            },
        )
    tamanho = DESTINO.stat().st_size
    print(f"{len(blocos)} blocos gravados em {DESTINO.relative_to(RAIZ)} ({tamanho / 1e6:.2f} MB)")
    (DESTINO.parent / "capinzal.pmtiles.json").write_text(json.dumps({
        "origem": url,
        "compilacao": data,
        "regiao": {"bbox": REGIAO, "zoom": [ZOOM_MIN, ZOOM_REGIAO]},
        "detalhe": {"bbox": BBOX, "zoom": [ZOOM_REGIAO + 1, ZOOM_MAX]},
        "blocos": len(blocos),
        "bytes": tamanho,
        "licenca": "Dados © colaboradores do OpenStreetMap (ODbL 1.0). Esquema e compilação: Protomaps Basemaps.",
    }, ensure_ascii=False, indent=2) + "\n")


if __name__ == "__main__":
    main()
