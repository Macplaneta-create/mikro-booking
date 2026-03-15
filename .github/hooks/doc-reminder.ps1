$payload = @{
  continue = $true
  systemMessage = "Documentation reminder: after substantial coding, update STATUS.md and NEXT_SESSION.md, then check whether ROADMAP.md or checklists also need changes."
} | ConvertTo-Json -Compress

Write-Output $payload