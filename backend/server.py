"""
NadiPlayer FastAPI backend — mirror of the PHP API for Emergent preview.
Endpoints match the original PHP backend (and the prebuilt React SPA expects them).
Storage: MongoDB (collections: admins, site_config, pages).
Uploads: /app/backend/uploads/<filename>, served via GET /api/uploads/<filename>.
"""
from __future__ import annotations

import logging
import os
import re
import secrets
import time
import uuid
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Dict, List, Optional

import bcrypt
import jwt
from dotenv import load_dotenv
from fastapi import (
    APIRouter,
    Depends,
    FastAPI,
    File,
    Header,
    HTTPException,
    UploadFile,
)
from fastapi.responses import FileResponse, JSONResponse
from motor.motor_asyncio import AsyncIOMotorClient
from pydantic import BaseModel, Field
from starlette.middleware.cors import CORSMiddleware

# ---------------------------------------------------------------------------
# Config & paths
# ---------------------------------------------------------------------------

ROOT_DIR = Path(__file__).parent
load_dotenv(ROOT_DIR / ".env")

UPLOAD_DIR = ROOT_DIR / "uploads"
UPLOAD_DIR.mkdir(parents=True, exist_ok=True)

JWT_SECRET = os.environ.get("JWT_SECRET", "change-me")
JWT_ALG = "HS256"
JWT_EXP_SECONDS = 7 * 24 * 60 * 60  # 7 days

ADMIN_USERNAME = os.environ.get("ADMIN_USERNAME", "admin")
ADMIN_PASSWORD = os.environ.get("ADMIN_PASSWORD", "admin123")
MAX_UPLOAD_BYTES = int(os.environ.get("MAX_UPLOAD_BYTES", str(8 * 1024 * 1024)))

ALLOWED_MIME = {
    "image/png", "image/jpeg", "image/jpg", "image/webp",
    "image/gif", "image/svg+xml", "image/avif",
}
EXT_FROM_MIME = {
    "image/png": ".png",
    "image/jpeg": ".jpg",
    "image/jpg": ".jpg",
    "image/webp": ".webp",
    "image/gif": ".gif",
    "image/svg+xml": ".svg",
    "image/avif": ".avif",
}
ALLOWED_EXT = {".png", ".jpg", ".jpeg", ".webp", ".gif", ".svg", ".avif"}

# ---------------------------------------------------------------------------
# Mongo
# ---------------------------------------------------------------------------

mongo_url = os.environ["MONGO_URL"]
client = AsyncIOMotorClient(mongo_url)
db = client[os.environ["DB_NAME"]]

admins_col = db["admins"]
site_config_col = db["site_config"]
pages_col = db["pages"]

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


def now_iso() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S.000Z")


def hash_pw(plain: str) -> str:
    return bcrypt.hashpw(plain.encode("utf-8"), bcrypt.gensalt()).decode("utf-8")


def verify_pw(plain: str, hashed: str) -> bool:
    try:
        return bcrypt.checkpw(plain.encode("utf-8"), hashed.encode("utf-8"))
    except ValueError:
        return False


def jwt_create_admin_token(username: str) -> str:
    payload = {
        "sub": username,
        "role": "admin",
        "iat": int(time.time()),
        "exp": int(time.time()) + JWT_EXP_SECONDS,
    }
    return jwt.encode(payload, JWT_SECRET, algorithm=JWT_ALG)


def jwt_decode(token: str) -> Optional[Dict[str, Any]]:
    try:
        return jwt.decode(token, JWT_SECRET, algorithms=[JWT_ALG])
    except jwt.PyJWTError:
        return None


SLUG_RE = re.compile(r"^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$")


def normalize_slug(value: str) -> str:
    s = (value or "").strip().lower()
    s = re.sub(r"[^a-z0-9-]+", "-", s)
    s = s.strip("-")
    return s[:63]


def is_valid_slug(slug: str) -> bool:
    return bool(SLUG_RE.match(slug))


# ---------------------------------------------------------------------------
# Default site config (matches PHP config_default)
# ---------------------------------------------------------------------------


def config_default() -> Dict[str, str]:
    return {
        "theme": "premium",
        "gplay_url": "#",
        "appstore_url": "#",
        "appgallery_url": "#",
        "apk_url": "#",
        "contact_email": "nadiplayer@nadi.kr",
        "whatsapp_number": "+966500000000",
        "whatsapp_message": "Hello, I'm interested in NadiPlayer IPTV.",
        "hero_eyebrow_en": "Premium IPTV Experience",
        "hero_title_en": "Master Your TV",
        "hero_subtitle_en": "Watch +5000 live channels, +1200 movies and +500 series across phone, tablet and TV — in stunning quality.",
        "hero_eyebrow_ar": "تجربة IPTV فاخرة",
        "hero_title_ar": "تحكم في تلفازك",
        "hero_subtitle_ar": "شاهد أكثر من 5000 قناة و1200 فيلم و500 مسلسل على هاتفك ولوحيك وتلفازك بجودة مذهلة.",
        "sec1_title_en": "Master your TV.",
        "sec1_desc_en": "Live, on-demand, and your own subscriptions — all in one beautiful player.",
        "sec1_title_ar": "تحكم في تلفازك.",
        "sec1_desc_ar": "البث المباشر، حسب الطلب، واشتراكاتك الخاصة — كل ذلك في مشغل واحد فاخر.",
        "sec2_title_en": "Channels in the cloud.",
        "sec2_desc_en": "5000+ channels organized smartly with EPG, favourites and instant zapping.",
        "sec2_title_ar": "قنوات في السحابة.",
        "sec2_desc_ar": "أكثر من 5000 قناة منظمة بذكاء مع دليل البرامج والمفضلة والتنقل الفوري.",
        "sec3_title_en": "On all your screens.",
        "sec3_desc_en": "Phone, tablet, Android TV, Fire TV — one subscription, every device.",
        "sec3_title_ar": "على جميع شاشاتك.",
        "sec3_desc_ar": "هاتف، لوحي، Android TV، Fire TV — اشتراك واحد لكل الأجهزة.",
        "sec4_title_en": "Add your options.",
        "sec4_desc_en": "Bring your own Xtream Codes or M3U URL. We don't sell content — just the player.",
        "sec4_title_ar": "أضف خياراتك.",
        "sec4_desc_ar": "استخدم Xtream Codes أو رابط M3U الخاص بك. نحن لا نبيع المحتوى — فقط المشغل.",
        "price_1m": "$12.99",
        "price_6m": "$39.99",
        "price_12m": "$69.99",
        "subscribe_url": "#",
        "free_trial_url": "#",
        "logo_url": "",
        "hero_phone_url": "",
        "nebula_bg_url": "",
        "favicon_url": "",
        "cosmic_bg_url": "",
        "yellow_bg_url": "",
        "premium_bg_url": "",
        "screen_home_url": "",
        "screen_channels_url": "",
        "screen_movies_url": "",
        "screen_login_url": "",
    }


async def site_config_get() -> Dict[str, str]:
    doc = await site_config_col.find_one({"_id": "site_config"}, {"_id": 0, "data": 1})
    if not doc:
        defaults = config_default()
        await site_config_col.insert_one({
            "_id": "site_config",
            "data": defaults,
            "updated_at": now_iso(),
        })
        return defaults
    data = doc.get("data") or {}
    merged = {**config_default(), **data}
    return merged


async def site_config_save(payload: Dict[str, Any]) -> Dict[str, str]:
    current = await site_config_get()
    merged = {**config_default(), **current, **(payload or {})}
    allowed_keys = list(config_default().keys())
    clean: Dict[str, str] = {}
    for k in allowed_keys:
        v = merged.get(k, "")
        clean[k] = "" if v is None else str(v)
    if clean.get("theme") not in {"cosmic", "yellow", "premium"}:
        clean["theme"] = "cosmic"
    await site_config_col.update_one(
        {"_id": "site_config"},
        {"$set": {"data": clean, "updated_at": now_iso()}},
        upsert=True,
    )
    return clean


# ---------------------------------------------------------------------------
# Auth dependency
# ---------------------------------------------------------------------------


async def require_admin(authorization: Optional[str] = Header(default=None)) -> Dict[str, Any]:
    if not authorization or not authorization.lower().startswith("bearer "):
        raise HTTPException(status_code=401, detail="Not authenticated")
    token = authorization[7:].strip()
    payload = jwt_decode(token)
    if not payload or not payload.get("sub"):
        raise HTTPException(status_code=401, detail="Invalid or expired token")
    admin = await admins_col.find_one(
        {"username": payload["sub"]}, {"_id": 0, "username": 1, "role": 1}
    )
    if not admin:
        raise HTTPException(status_code=401, detail="Admin not found")
    return admin


# ---------------------------------------------------------------------------
# FastAPI app
# ---------------------------------------------------------------------------

app = FastAPI(title="NadiPlayer API")
api_router = APIRouter(prefix="/api")


@app.on_event("startup")
async def on_startup() -> None:
    # Seed admin user
    await admins_col.create_index("username", unique=True)
    await admins_col.delete_many({"username": {"$ne": ADMIN_USERNAME}})
    existing = await admins_col.find_one({"username": ADMIN_USERNAME})
    if not existing:
        await admins_col.insert_one({
            "username": ADMIN_USERNAME,
            "password_hash": hash_pw(ADMIN_PASSWORD),
            "role": "admin",
            "created_at": now_iso(),
        })
    elif not verify_pw(ADMIN_PASSWORD, existing.get("password_hash", "")):
        await admins_col.update_one(
            {"username": ADMIN_USERNAME},
            {"$set": {"password_hash": hash_pw(ADMIN_PASSWORD)}},
        )
    # Ensure default site config exists
    await site_config_get()


@app.on_event("shutdown")
async def on_shutdown() -> None:
    client.close()


# ---------------------------------------------------------------------------
# Models
# ---------------------------------------------------------------------------


class LoginIn(BaseModel):
    username: str
    password: str


class PageIn(BaseModel):
    slug: str
    title_en: str = ""
    title_ar: str = ""
    content_en: str = ""
    content_ar: str = ""
    show_in_nav: bool = False
    published: bool = True


def page_doc(r: Dict[str, Any]) -> Dict[str, Any]:
    return {
        "id": str(r.get("id") or r.get("_id") or ""),
        "slug": str(r.get("slug") or ""),
        "title_en": str(r.get("title_en") or ""),
        "title_ar": str(r.get("title_ar") or ""),
        "content_en": str(r.get("content_en") or ""),
        "content_ar": str(r.get("content_ar") or ""),
        "show_in_nav": bool(r.get("show_in_nav") or False),
        "published": bool(r.get("published", True)),
        "updated_at": str(r.get("updated_at") or ""),
    }


# ---------------------------------------------------------------------------
# Public endpoints
# ---------------------------------------------------------------------------


@api_router.get("/")
async def root() -> Dict[str, str]:
    return {"message": "NadiPlayer API (FastAPI)"}


@api_router.get("/site-config")
async def get_public_site_config() -> Dict[str, str]:
    return await site_config_get()


@api_router.get("/pages")
async def list_published_pages() -> List[Dict[str, Any]]:
    cursor = pages_col.find(
        {"published": True}, {"_id": 0}
    ).sort("updated_at", -1)
    rows = await cursor.to_list(length=500)
    return [page_doc(r) for r in rows]


@api_router.get("/pages/{slug}")
async def get_published_page_by_slug(slug: str) -> Dict[str, Any]:
    s = normalize_slug(slug)
    if not is_valid_slug(s):
        raise HTTPException(status_code=404, detail="Page not found")
    row = await pages_col.find_one({"slug": s, "published": True}, {"_id": 0})
    if not row:
        raise HTTPException(status_code=404, detail="Page not found")
    return page_doc(row)


@api_router.post("/auth/login")
async def auth_login(body: LoginIn) -> Dict[str, Any]:
    if not body.username or not body.password:
        raise HTTPException(status_code=400, detail="Username and password are required")
    row = await admins_col.find_one(
        {"username": body.username}, {"_id": 0, "username": 1, "password_hash": 1}
    )
    if not row or not verify_pw(body.password, row.get("password_hash", "")):
        raise HTTPException(status_code=401, detail="Invalid username or password")
    token = jwt_create_admin_token(row["username"])
    return {
        "access_token": token,
        "token_type": "bearer",
        "username": row["username"],
    }


@api_router.get("/auth/me")
async def auth_me(admin: Dict[str, Any] = Depends(require_admin)) -> Dict[str, Any]:
    return {"username": admin["username"], "role": admin.get("role", "admin")}


# ---------------------------------------------------------------------------
# Admin: site config
# ---------------------------------------------------------------------------


@api_router.get("/admin/site-config")
async def admin_get_site_config(_: Dict[str, Any] = Depends(require_admin)) -> Dict[str, str]:
    return await site_config_get()


@api_router.put("/admin/site-config")
async def admin_put_site_config(
    body: Dict[str, Any],
    _: Dict[str, Any] = Depends(require_admin),
) -> Dict[str, str]:
    return await site_config_save(body)


# ---------------------------------------------------------------------------
# Admin: pages CRUD
# ---------------------------------------------------------------------------


@api_router.get("/admin/pages")
async def admin_list_pages(_: Dict[str, Any] = Depends(require_admin)) -> List[Dict[str, Any]]:
    cursor = pages_col.find({}, {"_id": 0}).sort("updated_at", -1)
    rows = await cursor.to_list(length=1000)
    return [page_doc(r) for r in rows]


@api_router.post("/admin/pages")
async def admin_create_page(
    body: PageIn, _: Dict[str, Any] = Depends(require_admin)
) -> Dict[str, Any]:
    slug = normalize_slug(body.slug)
    if not is_valid_slug(slug):
        raise HTTPException(status_code=400, detail="Invalid slug. Use lowercase letters, numbers and dashes.")
    existing = await pages_col.find_one({"slug": slug}, {"_id": 1})
    if existing:
        raise HTTPException(status_code=409, detail="A page with this slug already exists")
    doc = {
        "id": str(uuid.uuid4()),
        "slug": slug,
        "title_en": body.title_en,
        "title_ar": body.title_ar,
        "content_en": body.content_en,
        "content_ar": body.content_ar,
        "show_in_nav": bool(body.show_in_nav),
        "published": bool(body.published),
        "updated_at": now_iso(),
    }
    await pages_col.insert_one(dict(doc))
    return page_doc(doc)


@api_router.put("/admin/pages/{page_id}")
async def admin_update_page(
    page_id: str,
    body: PageIn,
    _: Dict[str, Any] = Depends(require_admin),
) -> Dict[str, Any]:
    slug = normalize_slug(body.slug)
    if not is_valid_slug(slug):
        raise HTTPException(status_code=400, detail="Invalid slug")
    current = await pages_col.find_one({"id": page_id}, {"_id": 0})
    if not current:
        raise HTTPException(status_code=404, detail="Page not found")
    clash = await pages_col.find_one(
        {"slug": slug, "id": {"$ne": page_id}}, {"_id": 1}
    )
    if clash:
        raise HTTPException(status_code=409, detail="Slug already in use by another page")
    update = {
        "slug": slug,
        "title_en": body.title_en,
        "title_ar": body.title_ar,
        "content_en": body.content_en,
        "content_ar": body.content_ar,
        "show_in_nav": bool(body.show_in_nav),
        "published": bool(body.published),
        "updated_at": now_iso(),
    }
    await pages_col.update_one({"id": page_id}, {"$set": update})
    return page_doc({**update, "id": page_id})


@api_router.delete("/admin/pages/{page_id}")
async def admin_delete_page(
    page_id: str, _: Dict[str, Any] = Depends(require_admin)
) -> Dict[str, bool]:
    res = await pages_col.delete_one({"id": page_id})
    if res.deleted_count == 0:
        raise HTTPException(status_code=404, detail="Page not found")
    return {"ok": True}


# ---------------------------------------------------------------------------
# Admin: uploads
# ---------------------------------------------------------------------------


def _safe_filename(name: str) -> bool:
    if not name or "/" in name or ".." in name or "\\" in name:
        return False
    return True


@api_router.post("/admin/upload")
async def admin_upload(
    file: UploadFile = File(...),
    _: Dict[str, Any] = Depends(require_admin),
) -> Dict[str, Any]:
    data = await file.read()
    size = len(data)
    if size > MAX_UPLOAD_BYTES:
        raise HTTPException(
            status_code=413,
            detail=f"File too large (max {round(MAX_UPLOAD_BYTES / 1024 / 1024, 1)} MB)",
        )
    client_type = (file.content_type or "").lower()
    orig_name = (file.filename or "").lower()
    orig_ext = Path(orig_name).suffix or ""
    if orig_ext == ".jpeg":
        orig_ext = ".jpg"

    mime = client_type
    if mime not in ALLOWED_MIME:
        # SVG fallback
        if orig_name.endswith(".svg"):
            mime = "image/svg+xml"
        else:
            raise HTTPException(
                status_code=400,
                detail="Only PNG, JPG, WEBP, GIF, AVIF and SVG images are allowed",
            )

    if orig_ext not in ALLOWED_EXT:
        orig_ext = EXT_FROM_MIME.get(mime, ".bin")

    new_name = secrets.token_hex(16) + orig_ext
    dest = UPLOAD_DIR / new_name
    dest.write_bytes(data)
    try:
        os.chmod(dest, 0o644)
    except OSError:
        pass

    return {
        "url": f"/api/uploads/{new_name}",
        "filename": new_name,
        "size": size,
        "content_type": mime,
    }


@api_router.get("/admin/uploads")
async def admin_list_uploads(_: Dict[str, Any] = Depends(require_admin)) -> List[Dict[str, Any]]:
    items: List[Dict[str, Any]] = []
    for p in UPLOAD_DIR.iterdir():
        if not p.is_file() or p.name == ".gitkeep":
            continue
        st = p.stat()
        items.append({
            "url": f"/api/uploads/{p.name}",
            "filename": p.name,
            "size": st.st_size,
            "modified": datetime.fromtimestamp(st.st_mtime, tz=timezone.utc).strftime(
                "%Y-%m-%dT%H:%M:%SZ"
            ),
        })
    items.sort(key=lambda x: x["modified"], reverse=True)
    return items


@api_router.delete("/admin/uploads/{filename}")
async def admin_delete_upload(
    filename: str, _: Dict[str, Any] = Depends(require_admin)
) -> Dict[str, bool]:
    if not _safe_filename(filename):
        raise HTTPException(status_code=400, detail="Invalid filename")
    target = UPLOAD_DIR / filename
    if not target.is_file():
        raise HTTPException(status_code=404, detail="File not found")
    target.unlink()
    return {"ok": True}


@api_router.get("/uploads/{filename}")
async def serve_upload(filename: str) -> FileResponse:
    if not _safe_filename(filename):
        raise HTTPException(status_code=400, detail="Invalid filename")
    target = UPLOAD_DIR / filename
    if not target.is_file():
        raise HTTPException(status_code=404, detail="Not found")
    media = None
    suf = target.suffix.lower()
    if suf == ".svg":
        media = "image/svg+xml"
    elif suf in {".jpg", ".jpeg"}:
        media = "image/jpeg"
    elif suf == ".png":
        media = "image/png"
    elif suf == ".webp":
        media = "image/webp"
    elif suf == ".gif":
        media = "image/gif"
    elif suf == ".avif":
        media = "image/avif"
    return FileResponse(target, media_type=media)


# ---------------------------------------------------------------------------
# Wire up
# ---------------------------------------------------------------------------

app.include_router(api_router)

app.add_middleware(
    CORSMiddleware,
    allow_origins=os.environ.get("CORS_ORIGINS", "*").split(","),
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.exception_handler(HTTPException)
async def http_exception_handler(_request, exc: HTTPException) -> JSONResponse:
    return JSONResponse({"detail": exc.detail}, status_code=exc.status_code)


logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
)
logger = logging.getLogger(__name__)
